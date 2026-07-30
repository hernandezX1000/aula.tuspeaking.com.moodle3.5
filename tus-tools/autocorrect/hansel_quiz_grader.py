#!/usr/bin/env python3
"""
hansel_quiz_grader.py — tuSpeaking automatic quiz essay grader
==============================================================
Detects Moodle quiz attempts with essay questions in 'needsgrading' state,
evaluates them via Claude API, and writes the grade directly to the DB.

NOTE: No Moodle REST API endpoint exists for quiz essay grading, so this
script uses direct SQL. IP is not logged in mdl_logstore_standard_log.
This is acceptable: quizzes are not FUNDAE-critical entregables.

Verified state names (Moodle 3.5, from mdl_question_attempt_steps):
  - Pending:  state = 'needsgrading'
  - Graded:   state = 'mangrright'   (fraction = 1.0)
              state = 'mangrpartial' (0 < fraction < 1.0)
              state = 'mangrwrong'   (fraction = 0.0)

Usage:
    python3 hansel_quiz_grader.py              # Run live
    python3 hansel_quiz_grader.py --dry-run    # Preview, no DB writes

Cron (every 4 hours):
    0 */4 * * * /usr/bin/python3 /home/aulatuspeaking/scripts/hansel_quiz_grader.py >> /home/aulatuspeaking/logs/hansel_quiz_grader.log 2>&1

Dependencies:
    pip3 install pymysql anthropic --user
"""

import pymysql
import anthropic
import logging
import time
import sys
import re
import json
import os

# ──────────────────────────────────────────────────────────────
# CONFIGURATION — cargada desde /home/aulatuspeaking/.env
# ──────────────────────────────────────────────────────────────

def _load_env(path='/home/aulatuspeaking/.env'):
    if not os.path.exists(path):
        return
    with open(path) as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            k, v = line.split('=', 1)
            os.environ.setdefault(k.strip(), v.strip().strip('"\''))

_load_env()

DB_CONFIG = {
    # Post-migración: la BD del aula vive en Docker (TCP 127.0.0.1:3307), no en el
    # socket local. pymysql con host='localhost' iría por socket (Access denied 1698).
    'host':     os.environ.get('MOODLE_DB_HOST', '127.0.0.1'),
    'port':     int(os.environ.get('MOODLE_DB_PORT', '3307')),
    'user':     os.environ.get('MOODLE_DB_USER', 'moodle35'),
    'password': os.environ.get('MOODLE_DB_PASSWORD', ''),
    'database': os.environ.get('MOODLE_DB_NAME', 'aulatuspeaking35'),
    'charset':  'utf8mb4',
    'autocommit': False,
}

CLAUDE_API_KEY = os.environ.get('ANTHROPIC_API_KEY', '')
CLAUDE_MODEL   = 'claude-haiku-4-5-20251001'

GRADER_HANSEL = 14  # hfernandez@tuspeaking.com

# Delay between Claude API calls (seconds)
API_CALL_DELAY = 1.5

# Min answer length to attempt grading
MIN_ANSWER_CHARS = 5

# Submissions to skip — set of (quiz_attempt_id, question_attempt_id)
EXCLUDED_QA = set()

# ──────────────────────────────────────────────────────────────
# FLAGS
# ──────────────────────────────────────────────────────────────

DRY_RUN = '--dry-run' in sys.argv


def _parse_attempt_arg():
    """--attempt <mdl_quiz_attempts.id>: re-corrige ese intento aunque ya esté calificado."""
    for i, a in enumerate(sys.argv):
        if a == '--attempt' and i + 1 < len(sys.argv):
            try:
                return int(sys.argv[i + 1])
            except ValueError:
                return None
    return None


ATTEMPT_ID = _parse_attempt_arg()

# ──────────────────────────────────────────────────────────────
# LOGGING
# ──────────────────────────────────────────────────────────────

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
)
log = logging.getLogger('hansel_quiz')

# ──────────────────────────────────────────────────────────────
# HELPERS
# ──────────────────────────────────────────────────────────────

def strip_html(text: str) -> str:
    if not text:
        return ''
    clean = re.sub(r'<[^>]+>', ' ', text)
    clean = clean.replace('&nbsp;', ' ').replace('&amp;', '&')
    clean = clean.replace('&lt;', '<').replace('&gt;', '>').replace('&quot;', '"')
    clean = re.sub(r'\s+', ' ', clean).strip()
    return clean


def detect_level(course_name: str) -> str:
    cn = course_name.lower()
    for lvl in ['c1', 'b2', 'b1.2', 'b1', 'a2', 'a1']:
        if lvl in cn:
            return lvl.upper()
    return 'B1'


def detect_question_type(question_text: str) -> str:
    """
    Returns 'translation' if the question asks to translate phrases,
    'writing' otherwise.
    """
    qt = question_text.lower()
    if any(k in qt for k in ['translat', 'traduc', 'put into english', 'translate']):
        return 'translation'
    return 'writing'


def grade_to_state(fraction: float) -> str:
    """Map fraction (0.0–1.0) to Moodle 3.5 state name."""
    if fraction >= 1.0:
        return 'mangrright'
    if fraction <= 0.0:
        return 'mangrwrong'
    return 'mangrpartial'


def contains_emoji(text: str) -> bool:
    pattern = re.compile(
        "["
        u"\U0001F600-\U0001F64F"
        u"\U0001F300-\U0001F5FF"
        u"\U0001F680-\U0001F6FF"
        u"\U00002600-\U000027BF"
        u"\U0001F1E0-\U0001F1FF"
        "]+", flags=re.UNICODE
    )
    return bool(pattern.search(text))


def now_ts() -> int:
    return int(time.time())


# ──────────────────────────────────────────────────────────────
# DATABASE
# ──────────────────────────────────────────────────────────────

def get_db():
    return pymysql.connect(**DB_CONFIG, cursorclass=pymysql.cursors.DictCursor)


def fetch_pending_quiz_essays(conn, attempt_id=None):
    """
    Devuelve una fila por pregunta essay. Normalmente solo las que están en
    'needsgrading'; si se pasa attempt_id, coge TODAS las de ese intento aunque
    ya estén calificadas (re-corrección — save_quiz_grade añade un paso nuevo).
    """
    if attempt_id is not None:
        where_extra = "AND qa.id = %s"
    else:
        where_extra = (
            "AND last_step.state = 'needsgrading' "
            "AND qa.timestart >= UNIX_TIMESTAMP('2026-01-01') "
            "AND c.fullname NOT LIKE '%DEMO%' AND c.fullname NOT LIKE '%demo%' "
            "AND c.fullname NOT LIKE '%Prueba de nivel%' AND c.fullname NOT LIKE '%prueba de nivel%' "
            "AND c.fullname NOT LIKE '%Italiano%' AND c.fullname NOT LIKE '%italiano%'"
        )
    sql = f"""
    SELECT
        qa.id                   AS quiz_attempt_id,
        qa.quiz                 AS quiz_id,
        qa.userid               AS userid,
        qat.id                  AS question_attempt_id,
        qat.maxmark             AS maxmark,
        q.id                    AS question_id,
        q.questiontext          AS question_text,
        u.firstname             AS firstname,
        u.lastname              AS lastname,
        c.fullname              AS course_name,
        qz.name                 AS quiz_name,
        ans_step.id             AS answer_step_id,
        ans_data.value          AS student_answer,
        qa.uniqueid             AS usage_id
    FROM mdl_quiz_attempts qa
    JOIN mdl_quiz qz            ON qz.id = qa.quiz
    JOIN mdl_course c           ON c.id  = qz.course
    JOIN mdl_user u             ON u.id  = qa.userid
    JOIN mdl_question_attempts qat ON qat.questionusageid = qa.uniqueid
    JOIN mdl_question q         ON q.id = qat.questionid AND q.qtype = 'essay'
    -- Last step for this question attempt (to check state = needsgrading)
    JOIN mdl_question_attempt_steps last_step
        ON last_step.questionattemptid = qat.id
        AND last_step.sequencenumber = (
            SELECT MAX(s2.sequencenumber)
            FROM mdl_question_attempt_steps s2
            WHERE s2.questionattemptid = qat.id
        )
    -- Step where the student saved their answer (state = 'complete')
    JOIN mdl_question_attempt_steps ans_step
        ON ans_step.questionattemptid = qat.id
        AND ans_step.state = 'complete'
        AND ans_step.sequencenumber = (
            SELECT MIN(s3.sequencenumber)
            FROM mdl_question_attempt_steps s3
            WHERE s3.questionattemptid = qat.id AND s3.state = 'complete'
        )
    JOIN mdl_question_attempt_step_data ans_data
        ON ans_data.attemptstepid = ans_step.id
        AND ans_data.name = 'answer'
    WHERE qa.state = 'finished'
      {where_extra}
    ORDER BY qa.timestart ASC
    LIMIT 30
    """
    with conn.cursor() as cur:
        if attempt_id is not None:
            cur.execute(sql, (attempt_id,))
        else:
            cur.execute(sql)
        return cur.fetchall()


def save_quiz_grade(conn, question_attempt_id: int, quiz_attempt_id: int,
                    usage_id: int, quiz_id: int, userid: int,
                    fraction: float, maxmark: float, feedback: str):
    """
    Write manual grade for a quiz essay question.
    5-step SQL process verified against Moodle 3.5 schema.
    """
    state = grade_to_state(fraction)
    mark  = round(fraction * maxmark, 5)
    ts    = now_ts()

    # Sanitize feedback
    if contains_emoji(feedback):
        feedback = re.sub(r'[^\x00-\x7F]+', '', feedback)

    with conn.cursor() as cur:
        # 1. Get next sequence number
        cur.execute(
            "SELECT MAX(sequencenumber) AS maxseq FROM mdl_question_attempt_steps "
            "WHERE questionattemptid = %s",
            (question_attempt_id,)
        )
        row = cur.fetchone()
        next_seq = (row['maxseq'] or 0) + 1

        # 2. Insert grading step
        cur.execute(
            """
            INSERT INTO mdl_question_attempt_steps
                (questionattemptid, sequencenumber, state, fraction, timecreated, userid)
            VALUES (%s, %s, %s, %s, %s, %s)
            """,
            (question_attempt_id, next_seq, state, fraction, ts, GRADER_HANSEL)
        )
        new_step_id = cur.lastrowid

        # 3. Insert step data (comment + mark)
        step_data = [
            (new_step_id, '-comment',       feedback),
            (new_step_id, '-commentformat', '1'),
            (new_step_id, '-mark',          str(mark)),
            (new_step_id, '-maxmark',       str(maxmark)),
        ]
        cur.executemany(
            "INSERT INTO mdl_question_attempt_step_data (attemptstepid, name, value) "
            "VALUES (%s, %s, %s)",
            step_data
        )

        # 4. Recalculate quiz attempt sumgrades
        cur.execute(
            """
            UPDATE mdl_quiz_attempts
            SET sumgrades = (
                SELECT SUM(qat2.maxmark * COALESCE(last_qas.fraction, 0))
                FROM mdl_question_attempts qat2
                JOIN mdl_question_attempt_steps last_qas
                    ON last_qas.questionattemptid = qat2.id
                    AND last_qas.sequencenumber = (
                        SELECT MAX(s4.sequencenumber)
                        FROM mdl_question_attempt_steps s4
                        WHERE s4.questionattemptid = qat2.id
                    )
                WHERE qat2.questionusageid = %s
            )
            WHERE id = %s
            """,
            (usage_id, quiz_attempt_id)
        )

        # 5. Fetch updated sumgrades to compute final quiz grade
        cur.execute(
            "SELECT sumgrades FROM mdl_quiz_attempts WHERE id = %s",
            (quiz_attempt_id,)
        )
        attempt_row = cur.fetchone()
        sumgrades = attempt_row['sumgrades'] if attempt_row else 0.0

        # Get quiz grade settings (sumgrades → final grade scaling)
        cur.execute(
            "SELECT grade AS quiz_max FROM mdl_quiz WHERE id = %s",
            (quiz_id,)
        )
        quiz_row = cur.fetchone()
        quiz_max = float(quiz_row['quiz_max']) if quiz_row else 10.0

        # Get total max possible sumgrades for this quiz
        cur.execute(
            """
            SELECT SUM(qat3.maxmark) AS total_maxmark
            FROM mdl_question_attempts qat3
            WHERE qat3.questionusageid = %s
            """,
            (usage_id,)
        )
        total_row = cur.fetchone()
        total_maxmark = float(total_row['total_maxmark']) if total_row and total_row['total_maxmark'] else 1.0

        final_grade = round((float(sumgrades or 0) / total_maxmark) * quiz_max, 5) if total_maxmark > 0 else 0.0

        # 6. Upsert quiz final grade
        cur.execute(
            """
            INSERT INTO mdl_quiz_grades (quiz, userid, grade, timemodified)
            VALUES (%s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE grade = VALUES(grade), timemodified = VALUES(timemodified)
            """,
            (quiz_id, userid, final_grade, ts)
        )

    conn.commit()
    log.info(
        f"  [DB] Quiz grade saved — qa_id={quiz_attempt_id} qat_id={question_attempt_id} "
        f"fraction={fraction} mark={mark}/{maxmark} state={state} final_grade={final_grade}"
    )


# ──────────────────────────────────────────────────────────────
# CLAUDE API — QUIZ GRADERS
# ──────────────────────────────────────────────────────────────

def detect_translation_target(question_text: str) -> str:
    """
    For a translation question, returns the language the ANSWER must be in:
    'es' (Spanish) or 'en' (English), inferred from the prompt wording.
    Fixes the bug where an English->Spanish task rejected a correct Spanish answer.
    Default 'en' (English course => translate into English).
    """
    qt = question_text.lower()
    if any(k in qt for k in ['to spanish', 'into spanish', 'in spanish',
                             'al español', 'al espanol', 'al castellano',
                             'en español', 'en espanol', 'a español', 'a castellano']):
        return 'es'
    if any(k in qt for k in ['to english', 'into english', 'in english', 'put into english',
                             'al inglés', 'al ingles', 'en inglés', 'en ingles']):
        return 'en'
    return 'en'


def translation_system(target: str) -> str:
    """Grader prompt for a translation question, aware of the target language."""
    if target == 'es':
        direction = ("The student has translated English phrases into SPANISH. "
                     "A correct answer is written in Spanish — this is EXPECTED; "
                     "NEVER penalize an answer for being in Spanish or ask for it in English.")
        note = "Evaluate if each Spanish translation is correct, natural and faithful to the English original."
    else:
        direction = "The student has translated Spanish phrases into English."
        note = "Evaluate if each translation is correct, natural, and appropriate for the level."
    return f"""\
You are an English language teacher at a corporate academy.
{direction}
{note}

Return ONLY a valid JSON object:
{{"fraction": 0.8, "feedback": "Most translations are accurate and natural. Correction: \\"wrong phrase\\" -> \\"correct phrase\\"."}}

fraction rules:
- 1.0 = all or nearly all translations correct and natural
- 0.5 = partially correct, some errors or unnatural phrasing
- 0.0 = mostly incorrect or incomprehensible

feedback rules:
- 1-3 sentences in English
- No emojis
- MANDATORY: Quote at least one specific translation from the student's answer and comment on it.
- MANDATORY: If there is an error, use the format: Correction: "wrong phrase" -> "correct phrase".
- If all translations are correct, quote one and explain why it is natural or accurate.
- Do NOT state the numeric score
- NEVER write generic feedback not tied to a specific phrase in this submission.
"""

WRITING_SYSTEM = """\
You are an English language teacher at a corporate academy (tuSpeaking).
The student has answered a short writing or description question in a quiz.

Return ONLY a valid JSON object:
{"fraction": 0.75, "feedback": "Your use of 'however' to contrast ideas shows good cohesion. Correction: \"I am agree\" → \"I agree\" — 'agree' is not used with 'am'."}

fraction rules:
- 1.0 = excellent, task completed fully, appropriate vocabulary and grammar
- 0.75 = good, minor errors or missing detail
- 0.5 = partial, clear effort but significant gaps
- 0.25 = poor, very limited response
- 0.0 = no meaningful response

feedback rules:
- 2-3 sentences in English
- No emojis
- MANDATORY: Quote a specific word or phrase from the student's answer to anchor your feedback.
- MANDATORY: If there is a grammar or vocabulary error, quote it and give the correction in the format: Correction: "wrong phrase" → "correct phrase".
- If no errors, quote something specific and explain what could be expanded or improved.
- NEVER write generic feedback not tied to something in this specific answer.
- Do NOT state the numeric score
"""


def call_claude_quiz(question_text: str, student_answer: str,
                     question_type: str, level: str) -> dict:
    """
    Evaluate a quiz essay answer with Claude.
    Returns dict with 'fraction' (float 0-1) and 'feedback' (str).
    """
    client = anthropic.Anthropic(api_key=CLAUDE_API_KEY)

    if question_type == 'translation':
        # Direction-aware: e.g. "translate from English to Spanish" expects a Spanish answer.
        system = translation_system(detect_translation_target(question_text))
    else:
        system = WRITING_SYSTEM

    user_msg = (
        f"Level: {level}\n"
        f"Question: {strip_html(question_text)[:800]}\n\n"
        f"Student answer:\n{strip_html(student_answer)[:2000]}\n\n"
        f"Return ONLY the JSON object."
    )

    response = client.messages.create(
        model=CLAUDE_MODEL,
        max_tokens=300,
        system=system,
        messages=[{'role': 'user', 'content': user_msg}],
    )

    raw = response.content[0].text.strip()
    match = re.search(r'\{[^{}]+\}', raw, re.DOTALL)
    if not match:
        raise ValueError(f"Claude returned non-JSON: {raw[:200]}")

    data     = json.loads(match.group())
    fraction = float(data.get('fraction', 0.5))
    feedback = str(data.get('feedback', '')).strip()

    if not (0.0 <= fraction <= 1.0):
        raise ValueError(f"Fraction out of range: {fraction}")
    if len(feedback) < 10:
        raise ValueError(f"Feedback too short: {feedback}")
    if contains_emoji(feedback):
        feedback = re.sub(r'[^\x00-\x7F]+', '', feedback)

    return {'fraction': fraction, 'feedback': feedback}


# ──────────────────────────────────────────────────────────────
# MAIN PIPELINE
# ──────────────────────────────────────────────────────────────

def process_quiz_essays(conn) -> int:
    rows = fetch_pending_quiz_essays(conn, ATTEMPT_ID)
    log.info(f"Pending quiz essays found: {len(rows)}")

    processed = 0
    skipped   = 0

    for row in rows:
        quiz_attempt_id    = row['quiz_attempt_id']
        question_attempt_id = row['question_attempt_id']
        quiz_id            = row['quiz_id']
        userid             = row['userid']
        firstname          = row['firstname']
        lastname           = row['lastname']
        course             = row['course_name']
        quiz_name          = row['quiz_name']
        question_text      = row['question_text'] or ''
        student_answer     = row['student_answer'] or ''
        maxmark            = float(row['maxmark'] or 1.0)
        usage_id           = row['usage_id']
        level              = detect_level(course)
        q_type             = detect_question_type(question_text)

        log.info(
            f"Quiz essay: {firstname} {lastname} | {course} | "
            f"{quiz_name} | type={q_type} level={level} maxmark={maxmark}"
        )

        # Guard: excluded
        if (quiz_attempt_id, question_attempt_id) in EXCLUDED_QA:
            log.warning("  SKIP — manually excluded")
            skipped += 1
            continue

        # Guard: empty answer
        plain_answer = strip_html(student_answer)
        if len(plain_answer) < MIN_ANSWER_CHARS:
            log.warning(f"  SKIP — answer too short ({len(plain_answer)} chars): {plain_answer!r}")
            skipped += 1
            continue

        log.info(f"  Answer ({len(plain_answer)} chars): {plain_answer[:120]}...")

        try:
            result   = call_claude_quiz(question_text, student_answer, q_type, level)
            fraction = result['fraction']
            feedback = result['feedback']

            log.info(f"  Claude: fraction={fraction} | {feedback[:80]}...")

            if DRY_RUN:
                log.info("  [DRY-RUN] Skipping DB write.")
            else:
                save_quiz_grade(
                    conn,
                    question_attempt_id=question_attempt_id,
                    quiz_attempt_id=quiz_attempt_id,
                    usage_id=usage_id,
                    quiz_id=quiz_id,
                    userid=userid,
                    fraction=fraction,
                    maxmark=maxmark,
                    feedback=feedback,
                )

            processed += 1
            time.sleep(API_CALL_DELAY)

        except Exception as e:
            log.error(f"  ERROR — {firstname} {lastname} | {quiz_name} — {e}")
            try:
                conn.rollback()
            except Exception:
                pass

    log.info(f"Quiz grader done — processed={processed} skipped={skipped}")
    return processed


def main():
    mode = 'DRY-RUN' if DRY_RUN else 'LIVE'
    log.info(f"=== hansel_quiz_grader.py START [{mode}] ===")

    if not CLAUDE_API_KEY:
        log.error("ANTHROPIC_API_KEY not set. Export it or inject via crontab.")
        sys.exit(1)

    try:
        conn = get_db()
        process_quiz_essays(conn)
        conn.close()
    except Exception as e:
        log.error(f"Fatal error: {e}")
        sys.exit(1)

    log.info("=== hansel_quiz_grader.py END ===")


if __name__ == '__main__':
    main()

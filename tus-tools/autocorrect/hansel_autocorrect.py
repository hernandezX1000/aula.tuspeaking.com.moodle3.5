#!/usr/bin/env python3
"""
hansel_autocorrect.py — tuSpeaking automatic grading daemon
===========================================================
Detects pending Moodle writing and audio submissions,
evaluates writings via Claude API, assigns grades and feedback,
and marks activities as complete.

Writings are picked up whether pasted into the online text box OR
uploaded as a file attachment (docx/pdf/odt/txt/rtf).

Usage:
    python3 hansel_autocorrect.py              # Run once (live)
    python3 hansel_autocorrect.py --dry-run    # Preview — no DB writes
    python3 hansel_autocorrect.py --writings   # Only writings
    python3 hansel_autocorrect.py --audio      # Only audio

Cron (run every 2 hours):
    0 */2 * * * /usr/bin/python3 /home/aulatuspeaking/scripts/hansel_autocorrect.py >> /home/aulatuspeaking/logs/hansel_autocorrect.log 2>&1

Dependencies:
    pip3 install --user mysql-connector-python anthropic faster-whisper \
        python-docx pypdf odfpy striprtf
"""

import mysql.connector
import anthropic
import logging
import time
import sys
import re
import json
import os
from datetime import datetime

# ──────────────────────────────────────────────────────────────
# CONFIGURATION — loaded from /home/aulatuspeaking/.env
# ──────────────────────────────────────────────────────────────

def _load_env(path='/home/aulatuspeaking/.env'):
    """Load key=value pairs from .env into os.environ (does not override existing vars)."""
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
    'host':      'localhost',
    'user':      os.environ.get('MOODLE_DB_USER', 'moodle35'),
    'password':  os.environ.get('MOODLE_DB_PASSWORD', ''),
    'database':  os.environ.get('MOODLE_DB_NAME', 'aulatuspeaking35'),
    'charset':   'utf8mb4',
    'collation': 'utf8mb4_unicode_ci',
    'autocommit': False,
}

CLAUDE_API_KEY = os.environ.get('ANTHROPIC_API_KEY', '')

# Model: haiku = fastest/cheapest for grading; sonnet for higher quality
CLAUDE_MODEL = 'claude-haiku-4-5-20251001'

# Moodle REST API
MOODLE_WS_URL   = 'https://aula.tuspeaking.com/app/moodle/webservice/rest/server.php'
MOODLE_WS_TOKEN = os.environ.get('MOODLE_WS_TOKEN', '')  # hfernandez (user 14)

# Moodle user IDs for graders
GRADER_HANSEL   = 14    # hfernandez@tuspeaking.com
GRADER_EXTERNAL = 4414  # live@live.tuspeaking.com (Tutors tuSpeaking)

# Courses graded by external tutor account (case-insensitive substring match)
EXTERNAL_GRADER_KEYWORDS = ['velcro', 'capitole']

# French courses → nota_max=100 (Salvi, Bydemes, GDES Frances)
FRENCH_KEYWORDS = ['salvi', 'bydemes', 'frances', 'francés', 'french']

# Minimum plain-text length to attempt grading (skip empty/too-short)
MIN_WRITING_CHARS = 80

# Submissions to never grade (confirmed AI, Spanish courses, invalid, etc.)
# Format: (assignment_id, userid)  — add entries as needed
EXCLUDED_SUBMISSIONS = {
    (29922, 5790),   # Paula Fernández — Torres y Carrera B1 — ChatGPT confirmed (sub 41851)
    (23264, 2837),   # Ceferino Rivadeneira — Lactalis B2 — Copilot confirmed (sub 41994)
}

# Delay between Claude API calls (seconds) — avoid rate limits
API_CALL_DELAY = 1.5

# Moodle dataroot — physical location of uploaded files
MOODLE_DATAROOT = '/home/aulatuspeaking/www/app/moodle/data'

# Whisper model size: 'base' (fast, good enough), 'small' (better), 'medium' (slower)
WHISPER_MODEL_SIZE = 'base'

# Whisper model instance (loaded lazily on first audio)
_whisper_model = None

def get_whisper_model():
    global _whisper_model
    if _whisper_model is None:
        from faster_whisper import WhisperModel
        log.info("Loading Whisper model...")
        _whisper_model = WhisperModel(WHISPER_MODEL_SIZE, device='cpu', compute_type='int8')
        log.info("Whisper model ready.")
    return _whisper_model
AUDIO_FEEDBACK_FR = (
    "Bon travail, {firstname}! Votre enregistrement audio a ete recu et evalue. "
    "Vous faites de bons progres. Continuez ainsi!"
)

# ──────────────────────────────────────────────────────────────
# FLAGS
# ──────────────────────────────────────────────────────────────

DRY_RUN       = '--dry-run'  in sys.argv
ONLY_WRITINGS = '--writings' in sys.argv
ONLY_AUDIO    = '--audio'    in sys.argv

# ──────────────────────────────────────────────────────────────
# LOGGING
# ──────────────────────────────────────────────────────────────

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    datefmt='%Y-%m-%d %H:%M:%S',
)
log = logging.getLogger('hansel')


# ──────────────────────────────────────────────────────────────
# HELPERS
# ──────────────────────────────────────────────────────────────

def strip_html(text: str) -> str:
    """Remove HTML tags and entities from Moodle onlinetext."""
    if not text:
        return ''
    clean = re.sub(r'<[^>]+>', ' ', text)
    clean = clean.replace('&nbsp;', ' ').replace('&amp;', '&')
    clean = clean.replace('&lt;', '<').replace('&gt;', '>').replace('&quot;', '"')
    clean = re.sub(r'\s+', ' ', clean).strip()
    return clean


def detect_level(course_name: str) -> str:
    """Extract level from course name. Returns e.g. 'B1', 'B2', 'A2'."""
    cn = course_name.lower()
    # Check in specificity order
    for lvl in ['c1', 'b2', 'b1.2', 'b1', 'a2', 'a1']:
        if lvl in cn:
            return lvl.upper()
    return 'B1'  # safe default


def is_french(course_name: str) -> bool:
    cn = course_name.lower()
    return any(kw in cn for kw in FRENCH_KEYWORDS)


def get_grader(course_name: str) -> int:
    cn = course_name.lower()
    if any(kw in cn for kw in EXTERNAL_GRADER_KEYWORDS):
        return GRADER_EXTERNAL
    return GRADER_HANSEL


def get_nota_max(course_name: str) -> float:
    return 100.0 if is_french(course_name) else 10.0


def now_ts() -> int:
    return int(time.time())


def contains_emoji(text: str) -> bool:
    emoji_pattern = re.compile(
        "["
        u"\U0001F600-\U0001F64F"
        u"\U0001F300-\U0001F5FF"
        u"\U0001F680-\U0001F6FF"
        u"\U00002600-\U000027BF"
        u"\U0001F1E0-\U0001F1FF"
        "]+", flags=re.UNICODE
    )
    return bool(emoji_pattern.search(text))


# ──────────────────────────────────────────────────────────────
# FILE-ATTACHMENT WRITINGS — text extraction
# ──────────────────────────────────────────────────────────────
# Some students upload their writing as a file (docx/pdf/odt/txt/rtf)
# instead of pasting it into the online text box. Those submissions have
# empty onlinetext, so fetch_pending_writings never sees them. We extract
# the text from the attachment and grade it through the same pipeline.

DOC_EXTENSIONS   = ('docx', 'pdf', 'odt', 'txt', 'rtf', 'doc')
AUDIO_EXTENSIONS = ('wav', 'mp3', 'm4a', 'ogg', 'webm', 'aac', 'flac')


def file_ext(filename: str) -> str:
    return filename.rsplit('.', 1)[-1].lower() if '.' in (filename or '') else ''


def extract_text_from_file(filepath: str, filename: str) -> str:
    """Extract plain text from a writing attachment.
    Supports docx, pdf, odt, txt, rtf. Returns '' on failure/unsupported
    (e.g. scanned PDF with no text layer, or missing parser library)."""
    ext = file_ext(filename)
    try:
        if ext == 'txt':
            with open(filepath, 'r', errors='ignore') as f:
                return f.read().strip()
        if ext == 'docx':
            import docx  # python-docx
            d = docx.Document(filepath)
            return '\n'.join(p.text for p in d.paragraphs).strip()
        if ext == 'pdf':
            from pypdf import PdfReader
            reader = PdfReader(filepath)
            return '\n'.join((page.extract_text() or '') for page in reader.pages).strip()
        if ext == 'odt':
            from odf.opendocument import load as odf_load
            from odf.text import P
            from odf import teletype
            doc = odf_load(filepath)
            return '\n'.join(teletype.extractText(p) for p in doc.getElementsByType(P)).strip()
        if ext == 'rtf':
            from striprtf.striprtf import rtf_to_text
            with open(filepath, 'r', errors='ignore') as f:
                return rtf_to_text(f.read()).strip()
        log.warning(f"  Unsupported writing file type: .{ext}")
        return ''
    except Exception as e:
        log.error(f"  Text extraction failed (.{ext}): {e}")
        return ''


# ──────────────────────────────────────────────────────────────
# DATABASE
# ──────────────────────────────────────────────────────────────

def get_db():
    conn = mysql.connector.connect(**DB_CONFIG)
    conn.set_charset_collation('utf8mb4', 'utf8mb4_unicode_ci')
    return conn


def fetch_pending_writings(conn):
    """
    Returns writing submissions that have text content but no grade yet.
    Excludes submissions where onlinetext is empty, very short, or only HTML.
    """
    sql = """
    SELECT
        asub.assignment                 AS assignment,
        asub.userid                     AS userid,
        ao.onlinetext                   AS onlinetext,
        a.name                          AS assign_name,
        c.id                            AS course_id,
        c.fullname                      AS course_name,
        c.shortname                     AS course_shortname,
        u.firstname                     AS firstname,
        u.lastname                      AS lastname,
        asub.timecreated                AS submitted_at
    FROM mdl_assignsubmission_onlinetext ao
    JOIN mdl_assign_submission asub
        ON  asub.id     = ao.submission
        AND asub.latest = 1
        AND asub.status = 'submitted'
    JOIN mdl_assign a  ON a.id  = asub.assignment
    JOIN mdl_course c  ON c.id  = a.course
    JOIN mdl_user   u  ON u.id  = asub.userid
    LEFT JOIN mdl_assign_grades ag
        ON  ag.assignment = asub.assignment
        AND ag.userid     = asub.userid
    WHERE ag.id IS NULL
      AND ao.onlinetext IS NOT NULL
      AND ao.onlinetext != ''
      AND LENGTH(TRIM(ao.onlinetext)) > 10
      AND asub.timecreated >= UNIX_TIMESTAMP('2026-01-01')
      AND c.fullname NOT LIKE '%DEMO%'
      AND c.fullname NOT LIKE '%demo%'
      AND c.fullname NOT LIKE '%Prueba de nivel%'
      AND c.fullname NOT LIKE '%prueba de nivel%'
      AND c.fullname NOT LIKE '%Prueba Nivel%'
      AND c.fullname NOT LIKE '%prueba nivel%'
      AND c.fullname NOT LIKE '%Italiano%'
      AND c.fullname NOT LIKE '%italiano%'
    ORDER BY asub.timecreated ASC
    LIMIT 20
    """
    cur = conn.cursor(dictionary=True)
    cur.execute(sql)
    rows = cur.fetchall()
    cur.close()
    return rows


def fetch_pending_file_writings(conn):
    """
    Returns writing submissions delivered as an ATTACHED FILE
    (docx/pdf/odt/txt/rtf/doc) with no grade yet, where the online text box
    is empty or too short. Complements fetch_pending_writings (which only
    reads pasted online text). Audio-delivery assignments are excluded here
    (handled by fetch_pending_audio).
    """
    like_docs = " OR ".join([f"LOWER(f.filename) LIKE '%.{e}'" for e in DOC_EXTENSIONS])
    sql = f"""
    SELECT
        asub.assignment                 AS assignment,
        asub.userid                     AS userid,
        a.name                          AS assign_name,
        c.id                            AS course_id,
        c.fullname                      AS course_name,
        c.shortname                     AS course_shortname,
        u.firstname                     AS firstname,
        u.lastname                      AS lastname,
        asub.timecreated                AS submitted_at
    FROM mdl_assign_submission asub
    JOIN mdl_assign a  ON a.id  = asub.assignment
    JOIN mdl_course c  ON c.id  = a.course
    JOIN mdl_user   u  ON u.id  = asub.userid
    LEFT JOIN mdl_assign_grades ag
        ON  ag.assignment = asub.assignment
        AND ag.userid     = asub.userid
    LEFT JOIN mdl_assignsubmission_onlinetext ao
        ON  ao.submission = asub.id
    WHERE ag.id IS NULL
      AND asub.latest = 1
      AND asub.status = 'submitted'
      AND asub.timecreated >= UNIX_TIMESTAMP('2026-01-01')
      AND (ao.onlinetext IS NULL OR LENGTH(TRIM(ao.onlinetext)) <= 10)
      AND EXISTS (
          SELECT 1 FROM mdl_files f
          WHERE f.itemid    = asub.id
            AND f.component  = 'assignsubmission_file'
            AND f.filearea   = 'submission_files'
            AND f.filesize   > 0
            AND ({like_docs})
      )
      AND a.name NOT LIKE '%ENTREGA DE AUDIO%'
      AND a.name NOT LIKE '%Audio Delivery%'
      AND a.name NOT LIKE '%AUDIO DELIVERY%'
      AND a.name NOT LIKE '%entrega de audio%'
      AND c.fullname NOT LIKE '%DEMO%'
      AND c.fullname NOT LIKE '%demo%'
      AND c.fullname NOT LIKE '%Prueba de nivel%'
      AND c.fullname NOT LIKE '%prueba de nivel%'
      AND c.fullname NOT LIKE '%Prueba Nivel%'
      AND c.fullname NOT LIKE '%prueba nivel%'
      AND c.fullname NOT LIKE '%Italiano%'
      AND c.fullname NOT LIKE '%italiano%'
    ORDER BY asub.timecreated ASC
    LIMIT 20
    """
    cur = conn.cursor(dictionary=True)
    cur.execute(sql)
    rows = cur.fetchall()
    cur.close()
    return rows


def fetch_pending_audio(conn):
    """
    Returns audio delivery submissions with no grade.
    Matches assignments whose name contains 'ENTREGA DE AUDIO' or 'Audio Delivery'.
    """
    sql = """
    SELECT
        asub.assignment                 AS assignment,
        asub.userid                     AS userid,
        a.name                          AS assign_name,
        c.id                            AS course_id,
        c.fullname                      AS course_name,
        c.shortname                     AS course_shortname,
        u.firstname                     AS firstname,
        u.lastname                      AS lastname,
        asub.timecreated                AS submitted_at
    FROM mdl_assign_submission asub
    JOIN mdl_assign a  ON a.id  = asub.assignment
    JOIN mdl_course c  ON c.id  = a.course
    JOIN mdl_user   u  ON u.id  = asub.userid
    LEFT JOIN mdl_assign_grades ag
        ON  ag.assignment = asub.assignment
        AND ag.userid     = asub.userid
    WHERE ag.id IS NULL
      AND asub.status = 'submitted'
      AND asub.latest = 1
      AND asub.timecreated >= UNIX_TIMESTAMP('2026-01-01')
      AND c.fullname NOT LIKE '%DEMO%'
      AND c.fullname NOT LIKE '%demo%'
      AND c.fullname NOT LIKE '%Prueba de nivel%'
      AND c.fullname NOT LIKE '%prueba de nivel%'
      AND (
          a.name LIKE '%ENTREGA DE AUDIO%'
       OR a.name LIKE '%Audio Delivery%'
       OR a.name LIKE '%AUDIO DELIVERY%'
       OR a.name LIKE '%entrega de audio%'
      )
    ORDER BY asub.timecreated ASC
    LIMIT 20
    """
    cur = conn.cursor(dictionary=True)
    cur.execute(sql)
    rows = cur.fetchall()
    cur.close()
    return rows


def fetch_audio_file(conn, assignment: int, userid: int):
    """
    Returns the physical file info for an audio submission.
    Looks up mdl_files via the submission ID.
    """
    sql = """
    SELECT f.contenthash, f.filename, f.mimetype, f.filesize
    FROM mdl_files f
    JOIN mdl_assign_submission asub
        ON  asub.id        = f.itemid
        AND asub.assignment = %s
        AND asub.userid    = %s
        AND asub.latest    = 1
    WHERE f.component  = 'assignsubmission_file'
      AND f.filearea   = 'submission_files'
      AND f.filename  != '.'
      AND f.filesize   > 0
    ORDER BY f.id DESC
    LIMIT 1
    """
    cur = conn.cursor(dictionary=True)
    cur.execute(sql, (assignment, userid))
    row = cur.fetchone()
    cur.close()
    if not row:
        return None
    h = row['contenthash']
    row['filepath'] = f"{MOODLE_DATAROOT}/filedir/{h[0:2]}/{h[2:4]}/{h}"
    return row


def transcribe_audio(file_path: str, lang: str = 'en') -> str:
    """
    Transcribe audio file using faster-whisper.
    Returns plain text transcription or empty string on failure.
    """
    import os
    if not os.path.exists(file_path):
        log.warning(f"  Audio file not found: {file_path}")
        return ''
    try:
        model = get_whisper_model()
        segments, info = model.transcribe(file_path, language=lang, beam_size=3)
        text = ' '.join(s.text for s in segments).strip()
        log.info(f"  Whisper ({info.language}, {info.duration:.0f}s): {text[:100]}...")
        return text
    except Exception as e:
        log.error(f"  Whisper error: {e}")
        return ''


def detect_audio_language(course_name: str) -> str:
    """Detect spoken language from course name."""
    cn = course_name.lower()
    if any(k in cn for k in ['frances', 'francés', 'french', 'salvi', 'bydemes']):
        return 'fr'
    if 'portugu' in cn or 'portuguese' in cn:
        return 'pt'
    return 'en'


def insert_grade(conn, assignment: int, userid: int, grader: int, grade: float):
    ts = now_ts()
    sql = """
    INSERT INTO mdl_assign_grades
        (assignment, userid, timecreated, timemodified, grader, grade, attemptnumber)
    VALUES (%s, %s, %s, %s, %s, %s, 0)
    ON DUPLICATE KEY UPDATE
        timemodified = VALUES(timemodified),
        grade        = VALUES(grade),
        grader       = VALUES(grader)
    """
    cur = conn.cursor()
    cur.execute(sql, (assignment, userid, ts, ts, grader, round(grade, 5)))
    conn.commit()
    cur.close()
    log.info(f"  [DB] Grade OK — assign={assignment} user={userid} grade={grade}")


def insert_feedback(conn, assignment: int, userid: int, comment: str):
    # Ensure no emojis slip through
    if contains_emoji(comment):
        comment = re.sub(r'[^\x00-\x7F]+', '', comment)

    sql = """
    INSERT INTO mdl_assignfeedback_comments (assignment, grade, commenttext, commentformat)
    SELECT %s, ag.id, %s, 1
    FROM mdl_assign_grades ag
    WHERE ag.assignment = %s AND ag.userid = %s
    ON DUPLICATE KEY UPDATE commenttext = VALUES(commenttext)
    """
    cur = conn.cursor()
    cur.execute(sql, (assignment, comment, assignment, userid))
    conn.commit()
    cur.close()
    log.info(f"  [DB] Feedback OK — assign={assignment} user={userid}")


def update_completion(conn, assignment: int, userid: int):
    """Fallback SQL completion update (only used if REST API fails)."""
    sql = """
    INSERT INTO mdl_course_modules_completion
        (coursemoduleid, userid, completionstate, timemodified, viewed)
    SELECT cm.id, %s, 1, %s, 1
    FROM mdl_course_modules cm
    JOIN mdl_modules m ON m.id = cm.module AND m.name = 'assign'
    WHERE cm.instance = %s
      AND cm.completion > 0
    ON DUPLICATE KEY UPDATE
        completionstate = 1,
        timemodified    = VALUES(timemodified)
    """
    ts = now_ts()
    cur = conn.cursor()
    cur.execute(sql, (userid, ts, assignment))
    conn.commit()
    cur.close()


def moodle_save_grade(assignment: int, userid: int, grade: float, feedback: str):
    """
    Submit grade + feedback via Moodle REST API (mod_assign_save_grade).
    This properly fires all Moodle events:
      - IP logging in mdl_logstore_standard_log
      - Student grade notification
      - Activity completion
    Returns True on success, raises RuntimeError on failure.
    """
    import urllib.request
    import urllib.parse

    # Sanitize feedback — no emojis
    if contains_emoji(feedback):
        feedback = re.sub(r'[^\x00-\x7F]+', '', feedback)

    params = {
        'wstoken':      MOODLE_WS_TOKEN,
        'wsfunction':   'mod_assign_save_grade',
        'moodlewsrestformat': 'json',
        'assignmentid': str(assignment),
        'userid':       str(userid),
        'grade':        str(round(grade, 5)),
        'attemptnumber': '-1',
        'addattempt':   '0',
        'workflowstate': 'graded',
        'applytoall':   '0',
        'plugindata[assignfeedbackcomments_editor][text]':   feedback,
        'plugindata[assignfeedbackcomments_editor][format]': '1',
    }

    data = urllib.parse.urlencode(params).encode('utf-8')
    req  = urllib.request.Request(MOODLE_WS_URL, data=data, method='POST')

    with urllib.request.urlopen(req, timeout=30) as resp:
        raw    = resp.read().decode('utf-8')
        result = json.loads(raw)

    if isinstance(result, dict) and 'exception' in result:
        raise RuntimeError(
            f"Moodle API error: {result.get('message')} | {result.get('debuginfo', '')}"
        )

    log.info(f"  [API] Grade saved — assign={assignment} user={userid} grade={grade}")


# ──────────────────────────────────────────────────────────────
# CLAUDE API — WRITING GRADER
# ──────────────────────────────────────────────────────────────

GRADING_SYSTEM_PROMPT = """\
You are an experienced English language teacher at a corporate language academy (tuSpeaking).
Your task: read a student's writing submission, assess whether it was AI-generated, and if not, grade it.

STRICT RULES:
- ai_score: integer 0-100 estimating the probability this text was generated by AI (ChatGPT, Copilot, etc.).
  Signs of AI: unnaturally perfect grammar, generic structure, no personal voice, overly formal for the level,
  suspiciously long and well-organized for the declared level, absence of typical learner errors.
- Grade must be a number between 1.0 and 10.0 (steps of 0.5 allowed).
- Feedback must be 2 to 4 sentences in English.
- NO emojis anywhere in the feedback.
- Do NOT mention the numeric grade in the feedback text.
- MANDATORY: Quote a specific word, phrase, or sentence from the student's text to anchor your feedback.
- MANDATORY: If there is a grammar or vocabulary error, quote it and give the correction in the format: Correction: "wrong phrase" → "correct phrase".
- If no clear errors exist, quote a specific phrase they wrote and explain what could be expanded or improved.
- NEVER use generic phrases not tied to this specific text ("use more vocabulary", "improve your grammar") without quoting an example from this submission.
- Focus on: task completion, vocabulary range, grammar accuracy, coherence.

Respond ONLY with a valid JSON object in this exact format (no extra text):
{"ai_score": 10, "grade": 7.5, "feedback": "Your feedback sentence here. Another sentence here."}
"""

AI_FEEDBACK_ES = (
    "Hemos detectado que este texto parece haber sido generado con ayuda de inteligencia artificial. "
    "Por esa razon no podemos evaluarlo ni certificar tus competencias en base a el. "
    "Si crees que es un error, elimina el texto actual y envía uno nuevo escrito por ti."
)
AI_SCORE_THRESHOLD = 85  # 0-100, above this → AI warning


def call_claude_writing(student_text: str, level: str, assign_name: str) -> dict:
    """
    Call Claude API to grade a writing submission.
    Returns dict with keys 'grade' (float), 'feedback' (str), 'ai_score' (int).
    Raises ValueError on unexpected response.
    """
    client = anthropic.Anthropic(api_key=CLAUDE_API_KEY)

    user_message = (
        f"Grade this {level} level English writing submission.\n"
        f"Assignment title: {assign_name}\n\n"
        f"--- STUDENT TEXT ---\n"
        f"{student_text[:3500]}\n"
        f"--- END TEXT ---\n\n"
        f"Return ONLY the JSON object as instructed."
    )

    response = client.messages.create(
        model=CLAUDE_MODEL,
        max_tokens=400,
        system=GRADING_SYSTEM_PROMPT,
        messages=[{'role': 'user', 'content': user_message}],
    )

    raw = response.content[0].text.strip()

    # Extract JSON — Claude sometimes adds trailing text
    match = re.search(r'\{[^{}]+\}', raw, re.DOTALL)
    if not match:
        raise ValueError(f"Claude returned non-JSON: {raw[:200]}")

    data = json.loads(match.group())

    ai_score = int(data.get('ai_score', 0))
    grade    = float(data.get('grade', 0))
    feedback = str(data.get('feedback', '')).strip()

    if not (1.0 <= grade <= 10.0):
        raise ValueError(f"Grade out of range: {grade}")
    if len(feedback) < 20:
        raise ValueError(f"Feedback too short: {feedback}")
    if contains_emoji(feedback):
        raise ValueError(f"Feedback contains emoji")

    return {'grade': grade, 'feedback': feedback, 'ai_score': ai_score}


# ──────────────────────────────────────────────────────────────
# AUDIO — WHISPER + CLAUDE GRADER
# ──────────────────────────────────────────────────────────────

AUDIO_GRADING_SYSTEM = """\
You are an experienced language teacher at a corporate academy evaluating a spoken audio submission.
You receive the automatic transcription of a student's recording.

STRICT RULES:
- Grade: 1.0-10.0 (steps of 0.5). For French/Portuguese courses multiply by 10 (caller handles scaling).
- Feedback: 2-4 sentences, no emojis, do not mention the grade number.
- MANDATORY: Reference something the student actually said — quote a word, phrase, or specific idea from the transcription.
- MANDATORY: If there is a grammar or vocabulary error in the transcription, quote it and give the correction in the format: Correction: "wrong phrase" → "correct phrase".
- If no clear errors exist, name a specific aspect of what they said that could be expanded or improved.
- NEVER use generic phrases that could apply to any student ("speak more slowly", "add more details") unless tied to something specific in this transcription.
- If transcription is empty or incoherent (< 10 words), grade 3.0 and say the audio could not be assessed.
- Evaluate: fluency, vocabulary range, grammar, task completion, clarity.

Return ONLY valid JSON:
{"grade": 7.0, "feedback": "Feedback here."}
"""

def call_claude_audio(transcription: str, level: str, assign_name: str, lang: str) -> dict:
    """
    Call Claude to evaluate an audio transcription.
    Returns dict with 'grade' (float) and 'feedback' (str).
    """
    client = anthropic.Anthropic(api_key=CLAUDE_API_KEY)
    lang_label = {'en': 'English', 'fr': 'French', 'pt': 'Portuguese'}.get(lang, 'English')

    user_message = (
        f"Evaluate this {level} level {lang_label} spoken submission.\n"
        f"Assignment: {assign_name}\n\n"
        f"--- TRANSCRIPTION ---\n"
        f"{transcription[:3000] if transcription else '[empty — no speech detected]'}\n"
        f"--- END ---\n\n"
        f"Return ONLY the JSON object."
    )

    response = client.messages.create(
        model=CLAUDE_MODEL,
        max_tokens=450,
        system=AUDIO_GRADING_SYSTEM,
        messages=[{'role': 'user', 'content': user_message}],
    )
    raw = response.content[0].text.strip()
    match = re.search(r'\{[^{}]+\}', raw, re.DOTALL)
    if not match:
        raise ValueError(f"Claude audio returned non-JSON: {raw[:200]}")
    data  = json.loads(match.group())
    grade = float(data.get('grade', 5.0))
    feedback = str(data.get('feedback', '')).strip()
    if not (1.0 <= grade <= 10.0):
        raise ValueError(f"Audio grade out of range: {grade}")
    return {'grade': grade, 'feedback': feedback}


def get_audio_feedback(firstname: str, level: str, french: bool) -> str:
    if french:
        return AUDIO_FEEDBACK_FR.format(firstname=firstname)
    return AUDIO_FEEDBACK_EN.format(firstname=firstname, level=level)


# ──────────────────────────────────────────────────────────────
# AI DETECTION — SKIP OBVIOUS AI SUBMISSIONS
# ──────────────────────────────────────────────────────────────

AI_MARKERS = [
    'as an ai', 'as an artificial intelligence', 'i am an ai',
    'i cannot provide', 'i\'m unable to', 'as a language model',
    'chatgpt', 'openai', 'copilot', 'generated by',
]

def looks_like_ai(text: str) -> bool:
    tl = text.lower()
    return any(m in tl for m in AI_MARKERS)


# ──────────────────────────────────────────────────────────────
# PIPELINES
# ──────────────────────────────────────────────────────────────

def process_writings(conn) -> int:
    rows = fetch_pending_writings(conn)
    log.info(f"Pending writings found: {len(rows)}")

    processed = 0
    skipped   = 0

    for row in rows:
        assign_id   = row['assignment']
        userid      = row['userid']
        course      = row['course_name']
        assign_name = row['assign_name']
        firstname   = row['firstname']
        lastname    = row['lastname']
        plain_text  = strip_html(row['onlinetext'])
        level       = detect_level(course)
        grader      = get_grader(course)
        nota_max    = get_nota_max(course)
        french      = is_french(course)
        days_old    = round((time.time() - row['submitted_at']) / 86400, 1)

        log.info(
            f"Writing: {firstname} {lastname} | {course} | {assign_name} | "
            f"level={level} grader={grader} nota_max={nota_max} ({days_old}d old)"
        )

        # Guard: manually excluded (confirmed AI or invalid)
        if (assign_id, userid) in EXCLUDED_SUBMISSIONS:
            log.warning(f"  SKIP — manually excluded (confirmed AI or invalid)")
            skipped += 1
            continue

        # Guard: too short
        if len(plain_text) < MIN_WRITING_CHARS:
            log.warning(f"  SKIP — text too short ({len(plain_text)} chars)")
            skipped += 1
            continue

        try:
            result   = call_claude_writing(plain_text, level, assign_name)
            grade_10 = result['grade']
            feedback = result['feedback']
            ai_score = result['ai_score']

            if ai_score >= AI_SCORE_THRESHOLD:
                # AI-generated: grade=4 + warning in Spanish
                grade_final = 40.0 if nota_max == 100.0 else 4.0
                feedback    = AI_FEEDBACK_ES
                log.warning(f"  AI DETECTED (score={ai_score}) — grade={grade_final} + aviso en espanol")
            else:
                # Normal submission
                grade_final = round(grade_10 * 10, 5) if nota_max == 100.0 else grade_10
                log.info(f"  ai_score={ai_score} | Grade: {grade_final}/{nota_max} | {feedback[:70]}...")

            if not DRY_RUN:
                moodle_save_grade(assign_id, userid, grade_final, feedback)

            processed += 1
            time.sleep(API_CALL_DELAY)

        except anthropic.APIError as e:
            log.error(f"  Claude API error: {e}")
            time.sleep(5)
        except Exception as e:
            log.error(f"  Error processing writing assign={assign_id} user={userid}: {e}")

    log.info(f"Writings — processed: {processed} | skipped: {skipped}")
    return processed


def process_file_writings(conn) -> int:
    """Grade writings that were uploaded as a file (docx/pdf/odt/txt/rtf)."""
    rows = fetch_pending_file_writings(conn)
    log.info(f"Pending file-writings found: {len(rows)}")

    processed = 0
    skipped   = 0

    for row in rows:
        assign_id   = row['assignment']
        userid      = row['userid']
        course      = row['course_name']
        assign_name = row['assign_name']
        firstname   = row['firstname']
        lastname    = row['lastname']
        level       = detect_level(course)
        grader      = get_grader(course)
        nota_max    = get_nota_max(course)
        days_old    = round((time.time() - row['submitted_at']) / 86400, 1)

        log.info(
            f"File-writing: {firstname} {lastname} | {course} | {assign_name} | "
            f"level={level} grader={grader} nota_max={nota_max} ({days_old}d old)"
        )

        # Guard: manually excluded (confirmed AI or invalid)
        if (assign_id, userid) in EXCLUDED_SUBMISSIONS:
            log.warning(f"  SKIP — manually excluded (confirmed AI or invalid)")
            skipped += 1
            continue

        try:
            # 1. Resolve the physical file (reuses the generic file lookup)
            file_info = fetch_audio_file(conn, assign_id, userid)
            if not file_info:
                log.warning(f"  SKIP — no attached file found in mdl_files")
                skipped += 1
                continue

            log.info(f"  File: {file_info['filename']} ({file_info['filesize']} bytes)")

            # 2. Extract plain text from the document
            plain_text = extract_text_from_file(file_info['filepath'], file_info['filename'])
            if len(plain_text) < MIN_WRITING_CHARS:
                log.warning(f"  SKIP — extracted text too short ({len(plain_text)} chars)")
                skipped += 1
                continue

            # 3. Grade through the same writing pipeline
            result   = call_claude_writing(plain_text, level, assign_name)
            grade_10 = result['grade']
            feedback = result['feedback']
            ai_score = result['ai_score']

            if ai_score >= AI_SCORE_THRESHOLD:
                grade_final = 40.0 if nota_max == 100.0 else 4.0
                feedback    = AI_FEEDBACK_ES
                log.warning(f"  AI DETECTED (score={ai_score}) — grade={grade_final} + aviso en espanol")
            else:
                grade_final = round(grade_10 * 10, 5) if nota_max == 100.0 else grade_10
                log.info(f"  ai_score={ai_score} | Grade: {grade_final}/{nota_max} | {feedback[:70]}...")

            if not DRY_RUN:
                moodle_save_grade(assign_id, userid, grade_final, feedback)

            processed += 1
            time.sleep(API_CALL_DELAY)

        except anthropic.APIError as e:
            log.error(f"  Claude API error: {e}")
            time.sleep(5)
        except Exception as e:
            log.error(f"  Error processing file-writing assign={assign_id} user={userid}: {e}")

    log.info(f"File-writings — processed: {processed} | skipped: {skipped}")
    return processed


def process_audio(conn) -> int:
    rows = fetch_pending_audio(conn)
    log.info(f"Pending audio found: {len(rows)}")

    processed = 0

    for row in rows:
        assign_id   = row['assignment']
        userid      = row['userid']
        course      = row['course_name']
        assign_name = row['assign_name']
        firstname   = row['firstname']
        lastname    = row['lastname']
        level       = detect_level(course)
        grader      = get_grader(course)
        nota_max    = get_nota_max(course)
        french      = is_french(course)
        days_old    = round((time.time() - row['submitted_at']) / 86400, 1)

        log.info(
            f"Audio: {firstname} {lastname} | {course} | {assign_name} | "
            f"level={level} grader={grader} nota_max={nota_max} ({days_old}d old)"
        )

        try:
            # 1. Get audio file from Moodle filedir
            file_info = fetch_audio_file(conn, assign_id, userid)
            if not file_info:
                log.warning(f"  SKIP — no audio file found in mdl_files")
                continue

            log.info(f"  File: {file_info['filename']} ({file_info['filesize']} bytes)")

            # 2. Transcribe with Whisper
            lang          = detect_audio_language(course)
            transcription = transcribe_audio(file_info['filepath'], lang)

            if not transcription:
                log.warning(f"  Transcription empty — using fallback grade by level")
                grade    = get_audio_grade(course)
                feedback = get_audio_feedback(firstname, level, french)
            else:
                # 3. Evaluate with Claude
                result   = call_claude_audio(transcription, level, assign_name, lang)
                grade_10 = result['grade']
                feedback = result['feedback']
                grade    = round(grade_10 * 10, 5) if nota_max == 100.0 else grade_10
                log.info(f"  Grade: {grade}/{nota_max} | {feedback[:70]}...")

            if not DRY_RUN:
                moodle_save_grade(assign_id, userid, grade, feedback)

            processed += 1
            time.sleep(API_CALL_DELAY)

        except anthropic.APIError as e:
            log.error(f"  Claude API error: {e}")
            time.sleep(5)
        except Exception as e:
            log.error(f"  Error processing audio assign={assign_id} user={userid}: {e}")

    log.info(f"Audio — processed: {processed}")
    return processed


# ──────────────────────────────────────────────────────────────
# ENTRY POINT
# ──────────────────────────────────────────────────────────────

def main():
    log.info("=" * 60)
    log.info("hansel_autocorrect.py — START")
    log.info(f"Mode: {'DRY RUN' if DRY_RUN else 'LIVE'} | {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    log.info("=" * 60)

    if not CLAUDE_API_KEY or not CLAUDE_API_KEY.startswith('sk-ant-'):
        log.error("ANTHROPIC_API_KEY not set. Run: export ANTHROPIC_API_KEY='sk-ant-...'")
        sys.exit(1)

    conn = get_db()
    try:
        total_w  = 0
        total_fw = 0
        total_a  = 0

        if not ONLY_AUDIO:
            total_w  = process_writings(conn)
            total_fw = process_file_writings(conn)

        if not ONLY_WRITINGS:
            total_a = process_audio(conn)

        log.info("=" * 60)
        log.info(f"DONE — Writings: {total_w} | File-writings: {total_fw} | Audio: {total_a}")
        log.info("=" * 60)

    except Exception as e:
        log.critical(f"Fatal error: {e}", exc_info=True)
        sys.exit(1)
    finally:
        conn.close()


if __name__ == '__main__':
    main()

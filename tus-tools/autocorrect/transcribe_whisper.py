#!/usr/bin/env python3
import subprocess, sys, json, os

print("Instalando faster-whisper...", flush=True)
subprocess.run([sys.executable, '-m', 'pip', 'install', 'faster-whisper', '-q',
                '--user'], check=True)

from faster_whisper import WhisperModel

print("Cargando modelo 'base'...", flush=True)
model = WhisperModel("base", device="cpu", compute_type="int8")

# (sub_id, nombre, ruta_absoluta, idioma)
files = [
    (41124, "Begona Anton",        "/home/aulatuspeaking/www/app/moodle/data/filedir/77/da/77da3b316c496f6ce928c77908d0f1793dbbd88e", "en"),
    (41217, "Maria Florencia Perez","/home/aulatuspeaking/www/app/moodle/data/filedir/9b/4d/9b4df6d75e86b26cec7903f61f41093001456090", "en"),
    (41427, "Maria Florencia Perez","/home/aulatuspeaking/www/app/moodle/data/filedir/ee/e8/eee8e1033cc0baba42d3e15a4336d9eab7646dd7", "en"),
    (41488, "Maria Florencia Perez","/home/aulatuspeaking/www/app/moodle/data/filedir/25/27/25272a6cf0f1c3ef3e47cae02749f4fc5b7e66b7", "en"),
    (41490, "Carlos Saiz",          "/home/aulatuspeaking/www/app/moodle/data/filedir/f0/9b/f09bcbc08e0e288a79368cde08e134bf08665aac", "en"),
    (41501, "Edwin Hernandez",       "/home/aulatuspeaking/www/app/moodle/data/filedir/2a/e8/2ae85b02dadbabdc7a7e9426097eeeaaa778d88c", "en"),
    (41502, "Rolando Martinez",      "/home/aulatuspeaking/www/app/moodle/data/filedir/52/56/5256df46b0178bb7531f32b797312d201b96885e", "en"),
    (41504, "Ianire Flores",         "/home/aulatuspeaking/www/app/moodle/data/filedir/f4/f0/f4f0c799f6d73eacf89cf7f4a53dced01d7d00d7", "en"),
    (41505, "Ianire Flores",         "/home/aulatuspeaking/www/app/moodle/data/filedir/81/1b/811b596f6f6da19f89a3a207e1a9c68f9191d12e", "en"),
    (41517, "Aaron Galindo",         "/home/aulatuspeaking/www/app/moodle/data/filedir/9a/1f/9a1ff0ea8775ae1fb3df188145ed6b4cb472e5d7", "en"),
    (41543, "Catherine Salas",       "/home/aulatuspeaking/www/app/moodle/data/filedir/dc/65/dc65cb12af3d09a1fd749c322d3f6511b1608c7a", "it"),
    (41547, "Jorge Martinez",        "/home/aulatuspeaking/www/app/moodle/data/filedir/f1/ee/f1eebe96ea131cabfbc53d00dd9f63cb010d1cf7", "en"),
    (41558, "Aaron Galindo",         "/home/aulatuspeaking/www/app/moodle/data/filedir/1c/c3/1cc370e9bc7ce5e16fefb760e5a4728bad760fc6", "en"),
    (41566, "Raquel Lorenzo",        "/home/aulatuspeaking/www/app/moodle/data/filedir/9b/d6/9bd67bcccab79273f7ca9ada707fa67ba15e3186", "en"),
    (41574, "Raquel Lorenzo",        "/home/aulatuspeaking/www/app/moodle/data/filedir/f4/b1/f4b1fc848fe165bbd204e554db43fa06fc1a645b", "en"),
    (41611, "Luisa Chamorro",        "/home/aulatuspeaking/www/app/moodle/data/filedir/c6/15/c615344c08db6cf0ae13709e54a5437dabe924be", "en"),
    (41632, "Ana Floro",             "/home/aulatuspeaking/www/app/moodle/data/filedir/58/06/58069e79daaa073fd36ecc58ab42ca35c9d9a224", "en"),
    (41636, "Eduardo Centeno",       "/home/aulatuspeaking/www/app/moodle/data/filedir/cd/af/cdaf73078bc41484e025c04630067d75a6f2c390", "en"),
    (41646, "Edwin Hernandez",       "/home/aulatuspeaking/www/app/moodle/data/filedir/e2/5c/e25c4ea140183f5ec422ccbb318d66e4a8eb16c3", "en"),
    (41648, "Ana Gonzalez",          "/home/aulatuspeaking/www/app/moodle/data/filedir/77/c6/77c682653ed41dddac68ed3f42e35c3da9db559e", "en"),
    (41650, "Ester Calatrava",       "/home/aulatuspeaking/www/app/moodle/data/filedir/ec/1d/ec1ddde93c713dbdabb7a8f5561f59947b4af787", "en"),
    (41669, "Luisa Chamorro",        "/home/aulatuspeaking/www/app/moodle/data/filedir/cb/9f/cb9f9e0a1b6b45c17774a244e06423b72018f51f", "en"),
    (41673, "Berta Rodriguez",       "/home/aulatuspeaking/www/app/moodle/data/filedir/8e/a9/8ea96faf5dcd6eb8a3bac357f89d500f72ff1fda", "en"),
    (41681, "Eduardo Centeno",       "/home/aulatuspeaking/www/app/moodle/data/filedir/37/60/37609c5b5b253ad7b5b725d3bf63fe8bd0772aac", "en"),
    (41698, "Maria Linan",           "/home/aulatuspeaking/www/app/moodle/data/filedir/93/6a/936a5f0f34ede1d31b6534bb018e458ca67eb9de", "en"),
    (41712, "Sebastian Chiazzaro",   "/home/aulatuspeaking/www/app/moodle/data/filedir/67/d3/67d3b0231887a8d7c493a5037f98c56b5afd4a58", "en"),
    (41723, "Sebastian Chiazzaro",   "/home/aulatuspeaking/www/app/moodle/data/filedir/7c/70/7c708478ae21f29e65e4e865362a42e1efd08e75", "en"),
    (41733, "Ianire Flores",         "/home/aulatuspeaking/www/app/moodle/data/filedir/af/0d/af0da17164110d15a1f6b5f5d2263a03957d3b8a", "en"),
    (41734, "Ianire Flores",         "/home/aulatuspeaking/www/app/moodle/data/filedir/fd/18/fd181a1d361e7bc170e05ac9b70a7e4f2deb00ae", "en"),
    (41736, "Gabriela Benitez",      "/home/aulatuspeaking/www/app/moodle/data/filedir/a9/56/a956ecf19c84a5603ce4b70da68ce3a0629437ed", "en"),
    (41740, "Sebastian Chiazzaro",   "/home/aulatuspeaking/www/app/moodle/data/filedir/4e/71/4e714ac571ce93d1eef20b6cd240c68de96f0475", "en"),
    (41756, "Paula Fernandez",       "/home/aulatuspeaking/www/app/moodle/data/filedir/8d/c6/8dc63ace05adf5d7254dc7f0ddbb06d6b935ca20", "en"),
    (41765, "Eduardo Centeno",       "/home/aulatuspeaking/www/app/moodle/data/filedir/f8/30/f8305136c2b3be61fb2176b248c80689e73238c3", "en"),
    (41774, "Gabriela Benitez",      "/home/aulatuspeaking/www/app/moodle/data/filedir/69/b4/69b449f7b34fcb41dbaebba370aa50b781ab430a", "en"),
    (41807, "Elias Valdez",          "/home/aulatuspeaking/www/app/moodle/data/filedir/3c/45/3c45be92c5421aaac8ec29e9f9e2132c06bd664f", "pt"),
    (41821, "Marta Paez",            "/home/aulatuspeaking/www/app/moodle/data/filedir/da/cb/dacb101c1f17b0c2cbffe01080bf0bc055fd16f9", "en"),
    (41846, "Antonio Veiga",         "/home/aulatuspeaking/www/app/moodle/data/filedir/fb/0c/fb0c86c7682c0d0ede6a9aff8dcee6bff3047d77", "pt"),
    (41848, "Antonio Veiga",         "/home/aulatuspeaking/www/app/moodle/data/filedir/ac/61/ac616517936e8c6020f32ebc1686c73ee3e9d9da", "pt"),
    (41861, "Jorge Martinez",        "/home/aulatuspeaking/www/app/moodle/data/filedir/62/be/62beca4428639ac3c0a976ec1d3dc403ed253ea5", "en"),
    (41931, "Tanja Gassner",         "/home/aulatuspeaking/www/app/moodle/data/filedir/66/8b/668bfe87eac898cd17c2d1d33d326955202fe02f", "en"),
    (41932, "Sandy Garcia",          "/home/aulatuspeaking/www/app/moodle/data/filedir/a7/37/a7378fbe4bb31667ffe91fd32e0fff52204bde41", "fr"),
    (41934, "Sandy Garcia",          "/home/aulatuspeaking/www/app/moodle/data/filedir/44/0e/440e1619d6c7d52ddeb2fe9a487aff2aacb984bf", "fr"),
    (41939, "Adrian Cajas",          "/home/aulatuspeaking/www/app/moodle/data/filedir/22/4e/224edb1740d2b79861a45df6c5583ce716f62aa8", "en"),
    (41984, "Sandy Garcia",          "/home/aulatuspeaking/www/app/moodle/data/filedir/61/5e/615ef8835edd30189d43c3acd5ae071d5bbae537", "fr"),
    (41999, "Anna Pitarch",          "/home/aulatuspeaking/www/app/moodle/data/filedir/af/9e/af9e6bab0a18c73a1b78def5c82a0a0b6c404eaf", "en"),
    (42006, "Anna Pitarch",          "/home/aulatuspeaking/www/app/moodle/data/filedir/f1/d9/f1d9fd7fd208216dbde40dfbd1ff3e95c8907cf8", "en"),
    (42009, "Monica Ortuno",         "/home/aulatuspeaking/www/app/moodle/data/filedir/cb/59/cb595225f99c08fd212c0b1f401c5e93286d898f", "en"),
    (42018, "Monica Ortuno",         "/home/aulatuspeaking/www/app/moodle/data/filedir/6d/d3/6dd3d6108bd581649f6b6e06f5b73f1ea15c091a", "en"),
    (42032, "Manuel Rueda",          "/home/aulatuspeaking/www/app/moodle/data/filedir/e6/7e/e67e3e1a2cf61a636afb1a2ffc183add189a4c94", "en"),
]

results = []
total = len(files)
for i, (sub_id, name, ruta, lang) in enumerate(files, 1):
    print(f"[{i}/{total}] sub_id={sub_id} {name}...", flush=True)
    if not os.path.exists(ruta):
        results.append({"sub_id": sub_id, "name": name, "error": "archivo no encontrado"})
        print(f"  ERROR: no existe {ruta}", flush=True)
        continue
    try:
        segments, info = model.transcribe(ruta, language=lang, beam_size=3)
        text = " ".join([s.text for s in segments]).strip()
        results.append({"sub_id": sub_id, "name": name, "lang": lang, "text": text})
        print(f"  OK: {text[:120]}", flush=True)
    except Exception as e:
        results.append({"sub_id": sub_id, "name": name, "error": str(e)})
        print(f"  ERROR: {e}", flush=True)

out_path = "/tmp/transcriptions.json"
with open(out_path, "w", encoding="utf-8") as f:
    json.dump(results, f, ensure_ascii=False, indent=2)

print(f"\n\nListo. {len(results)} transcripciones guardadas en {out_path}")
print("\n=== JSON ===")
print(json.dumps(results, ensure_ascii=False, indent=2))

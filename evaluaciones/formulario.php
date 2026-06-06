<?php
/**
 * STUDENT EVALUATION FORM - TEACHERS
 * TuSpeaking v1
 */

$DB_CONFIG = ['host'=>'localhost','database'=>'aulatuspeaking35','user'=>'moodle35','password'=>'TuspeakingFix2025!'];
try { $pdo = new PDO("mysql:host={$DB_CONFIG['host']};dbname={$DB_CONFIG['database']};charset=utf8mb4",$DB_CONFIG['user'],$DB_CONFIG['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); } catch(PDOException $e) { die("Error: ".$e->getMessage()); }

$mensaje = ''; $error = ''; $enviado = false;

$empresas = $pdo->query("SELECT * FROM mdl_coding_empresas WHERE activo=1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $enviado = true;
    $mensaje = "✓ Evaluation submitted successfully (test mode)";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Student Evaluation - TuSpeaking</title>
<style>
*{box-sizing:border-box}
body{font-family:'Segoe UI',Arial,sans-serif;margin:0;padding:20px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);min-height:100vh}
.container{max-width:700px;margin:0 auto}
.card{background:#fff;border-radius:16px;padding:35px;box-shadow:0 10px 40px rgba(0,0,0,.2)}
.logo{text-align:center;margin-bottom:25px}
.logo img{height:60px}
h1{color:#1a1a2e;margin:0 0 10px;text-align:center;font-size:1.8em}
.subtitle{color:#666;text-align:center;margin-bottom:30px}
.form-group{margin-bottom:20px}
.form-group label{display:block;margin-bottom:8px;font-weight:600;color:#374151}
.form-group label span.required{color:#ef4444}
.form-control{width:100%;padding:12px 15px;border:2px solid #e5e7eb;border-radius:8px;font-size:15px;transition:border-color .2s}
.form-control:focus{outline:none;border-color:#667eea}
select.form-control{cursor:pointer}
textarea.form-control{min-height:100px;resize:vertical}
.row{display:grid;grid-template-columns:1fr 1fr;gap:15px}
.row-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px}
.section{background:#f8f9fa;padding:20px;border-radius:10px;margin-bottom:25px}
.section h3{margin:0 0 15px;color:#1a1a2e;font-size:1.1em;border-bottom:2px solid #667eea;padding-bottom:8px}
.btn{width:100%;padding:15px;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer;transition:transform .2s,box-shadow .2s}
.btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 5px 20px rgba(102,126,234,.4)}
.alert{padding:15px 20px;border-radius:8px;margin-bottom:20px;text-align:center}
.alert-success{background:#d1fae5;color:#065f46;border:1px solid #a7f3d0}
.alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
.nota-input{width:100%;padding:10px;border:2px solid #e5e7eb;border-radius:8px;font-size:18px;text-align:center;font-weight:600}
.nota-label{font-size:12px;color:#666;text-align:center;display:block;margin-top:5px}
.gracias{text-align:center;padding:40px 20px}
.gracias h2{color:#065f46;margin-bottom:15px}
.gracias p{color:#666}
footer{text-align:center;padding:20px;color:rgba(255,255,255,.7);font-size:.85em}
</style>
</head>
<body>
<div class="container">
<div class="card">
    <div class="logo">
        <img src="../certificados/logo_tuspeaking.png" alt="TuSpeaking" onerror="this.style.display='none'">
    </div>
    
    <?php if($enviado): ?>
    <div class="gracias">
        <h2>✓ Thank you!</h2>
        <p>The evaluation has been submitted successfully.</p>
        <p style="margin-top:20px"><a href="formulario.php" style="color:#667eea">Submit another evaluation</a></p>
    </div>
    <?php else: ?>
    
    <h1>📝 Student Evaluation</h1>
    <p class="subtitle">Complete the student evaluation at the end of the course</p>
    
    <?php if($mensaje):?><div class="alert alert-success"><?=$mensaje?></div><?php endif;?>
    <?php if($error):?><div class="alert alert-error"><?=$error?></div><?php endif;?>
    
    <form method="post">
        <div class="section">
            <h3>👤 General Information</h3>
            <div class="row">
                <div class="form-group">
                    <label>Company <span class="required">*</span></label>
                    <select name="empresa_id" class="form-control" required>
                        <option value="">Select...</option>
                        <?php foreach($empresas as $emp):?>
                        <option value="<?=$emp['id']?>"><?=htmlspecialchars($emp['nombre'])?></option>
                        <?php endforeach;?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Teacher <span class="required">*</span></label>
                    <input type="text" name="profesor" class="form-control" placeholder="Your name" required>
                </div>
            </div>
            <div class="row">
                <div class="form-group">
                    <label>Student Name <span class="required">*</span></label>
                    <input type="text" name="alumno_nombre" class="form-control" placeholder="Full name" required>
                </div>
                <div class="form-group">
                    <label>Level Evaluated <span class="required">*</span></label>
                    <select name="nivel_evaluado" class="form-control" required>
                        <option value="">Select...</option>
                        <option value="A1">A1</option>
                        <option value="A2">A2</option>
                        <option value="B1">B1</option>
                        <option value="B2">B2</option>
                        <option value="C1">C1</option>
                        <option value="C2">C2</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h3>📊 Grades (0-5)</h3>
            <div class="row-3">
                <div class="form-group">
                    <input type="number" name="nota_grammar" class="nota-input" min="0" max="5" step="0.1" placeholder="0-5" required>
                    <span class="nota-label">Grammar & Vocabulary</span>
                </div>
                <div class="form-group">
                    <input type="number" name="nota_pronunciation" class="nota-input" min="0" max="5" step="0.1" placeholder="0-5" required>
                    <span class="nota-label">Pronunciation</span>
                </div>
                <div class="form-group">
                    <input type="number" name="nota_communicative" class="nota-input" min="0" max="5" step="0.1" placeholder="0-5" required>
                    <span class="nota-label">Communicative Skills</span>
                </div>
            </div>
            <div class="row-3">
                <div class="form-group">
                    <input type="number" name="nota_oral" class="nota-input" min="0" max="10" step="0.1" placeholder="0-10" required>
                    <span class="nota-label">Oral Exam</span>
                </div>
                <div class="form-group">
                    <input type="number" name="nota_participacion" class="nota-input" min="0" max="10" step="0.1" placeholder="0-10" required>
                    <span class="nota-label">Participation</span>
                </div>
                <div class="form-group">
                    <input type="number" name="nota_homework" class="nota-input" min="0" max="10" step="0.1" placeholder="0-10" required>
                    <span class="nota-label">Homework</span>
                </div>
            </div>
        </div>
        
        <div class="section">
            <h3>📝 Recommendation</h3>
            <div class="form-group">
                <label>Recommendation for the student <span class="required">*</span></label>
                <select name="recomendacion" class="form-control" required>
                    <option value="">Select...</option>
                    <option value="subir_nivel">Move up a level</option>
                    <option value="mantener_nivel">Stay at current level</option>
                    <option value="bajar_nivel">Move down a level</option>
                </select>
            </div>
            <div class="form-group">
                <label>Recommended Level</label>
                <select name="nivel_recomendado" class="form-control">
                    <option value="">Same level</option>
                    <option value="A1">A1</option>
                    <option value="A2">A2</option>
                    <option value="B1">B1</option>
                    <option value="B2">B2</option>
                    <option value="C1">C1</option>
                    <option value="C2">C2</option>
                </select>
            </div>
        </div>
        
        <div class="section">
            <h3>💬 Comments</h3>
            <div class="form-group">
                <label>Justification / General evaluation <span class="required">*</span></label>
                <textarea name="justificacion" class="form-control" placeholder="Write your evaluation of the student..." required></textarea>
            </div>
            <div class="form-group">
                <label>Homework Comments</label>
                <textarea name="comentarios_homework" class="form-control" placeholder="Additional comments about homework..."></textarea>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">📤 Submit Evaluation</button>
    </form>
    <?php endif; ?>
</div>
<footer>TuSpeaking © 2025</footer>
</div>
</body>
</html>

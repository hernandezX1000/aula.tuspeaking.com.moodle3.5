var prevValues = {};
var acuityTypes = "";
var newValues = {};
var yearFilter = "2025,2026";
var showOnlyUnconfigured = true;

$('document').ready(function() {
    // Añadir modal para crear nuevo Acuity Type
    $("body").append(
        '<div id="newAcuityModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:10000; align-items:center; justify-content:center;">' +
        '<div style="background:white; border-radius:8px; padding:0; width:450px; max-width:90%; box-shadow:0 4px 20px rgba(0,0,0,0.3);">' +
        '<div style="background:linear-gradient(135deg, #008ba3, #00bcd4); color:white; padding:15px 20px; border-radius:8px 8px 0 0; display:flex; justify-content:space-between; align-items:center;">' +
        '<span style="font-weight:600; font-size:16px;">+ Crear Acuity Type</span>' +
        '<span id="closeNewAcuityModal" style="cursor:pointer; font-size:24px; line-height:1;">&times;</span>' +
        '</div>' +
        '<div style="padding:20px;">' +
        '<input type="hidden" id="newAcuityCourseId">' +
        '<div style="margin-bottom:15px;">' +
        '<label style="display:block; font-weight:600; margin-bottom:5px; color:#454545;">Curso Moodle:</label>' +
        '<div id="newAcuityCourseLabel" style="padding:10px; background:#f5f5f5; border-radius:4px; color:#666;"></div>' +
        '</div>' +
        '<div style="margin-bottom:15px;">' +
        '<label style="display:block; font-weight:600; margin-bottom:5px; color:#454545;">Acuity ID: <span style="color:#e74c3c;">*</span></label>' +
        '<input type="text" id="newAcuityId" placeholder="Ej: 87654321" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box; font-size:14px;">' +
        '<small style="color:#999; font-size:11px;">Copia el ID desde Acuity después de crear el appointment</small>' +
        '</div>' +
        '<div style="margin-bottom:20px;">' +
        '<label style="display:block; font-weight:600; margin-bottom:5px; color:#454545;">Nombre en Acuity: <span style="color:#e74c3c;">*</span></label>' +
        '<input type="text" id="newAcuityName" placeholder="Pega el nombre del appointment de Acuity" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box; font-size:14px;">' +
        '<small style="color:#999; font-size:11px;">El mismo nombre que pusiste en Acuity (para identificarlo)</small>' +
        '</div>' +
        '<div id="newAcuityError" style="display:none; background:#ffebee; color:#c62828; padding:10px; border-radius:4px; margin-bottom:15px; font-size:13px;"></div>' +
        '<div style="display:flex; gap:10px; justify-content:flex-end;">' +
        '<button id="cancelNewAcuity" style="padding:10px 20px; border:1px solid #ddd; background:white; border-radius:4px; cursor:pointer;">Cancelar</button>' +
        '<button id="saveNewAcuity" style="padding:10px 20px; border:none; background:#008ba3; color:white; border-radius:4px; cursor:pointer; font-weight:500;">✓ Crear y Asignar</button>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>'
    );

    // Añadir controles superiores
    $("#data").before(
        '<div style="background:white; padding:20px; border-radius:8px; margin-bottom:20px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">' +
        '<div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap; margin-bottom:15px;">' +
        '<label style="font-weight:600;">Años:</label>' +
        '<select id="yearFilter" style="padding:8px 12px; border:1px solid #ddd; border-radius:4px;">' +
        '<option value="2025,2026" selected>2025-2026</option>' +
        '<option value="2024,2025,2026">2024-2026</option>' +
        '<option value="ALL">Todos</option>' +
        '</select>' +
        '<label style="font-weight:600; margin-left:15px;">Mostrar:</label>' +
        '<select id="showFilter" style="padding:8px 12px; border:1px solid #ddd; border-radius:4px;">' +
        '<option value="unconfigured" selected>Solo SIN configurar (rápido)</option>' +
        '<option value="all">Todos los cursos</option>' +
        '</select>' +
        '<button id="loadCourses" style="background:#00bcd4; color:white; border:none; padding:8px 20px; border-radius:4px; cursor:pointer; font-weight:500;">Cargar</button>' +
        '<span style="margin-left:15px;"></span>' +
        '<input type="text" id="searchCourse" placeholder="Buscar curso..." style="padding:8px 12px; border:1px solid #ddd; border-radius:4px; width:200px;">' +
        '</div>' +
        '<div style="display:flex; gap:15px; align-items:center;">' +
        '<span id="statsInfo" style="color:#666;"></span>' +
        '<button id="saveTop" style="margin-left:auto; background:#008ba3; color:white; border:none; padding:10px 25px; border-radius:4px; cursor:pointer; font-weight:500;"><i class="fas fa-save"></i> Guardar Cambios</button>' +
        '</div>' +
        '</div>'
    );
    
    getCourse();
    
    $("#saveTop").on("click", function() {
        getNewValues();
    });
    
    $("#searchCourse").on("keyup", function() {
        var search = $(this).val().toLowerCase();
        if (search.length < 2) {
            $("div#data > div").show();
            $("div#data fieldset table tr").show();
            return;
        }
        $("div#data > div").each(function() {
            var found = false;
            $(this).find("table tr").each(function() {
                var courseName = $(this).find("td:first span").text().toLowerCase();
                if (courseName.indexOf(search) > -1) {
                    $(this).show();
                    found = true;
                } else {
                    $(this).hide();
                }
            });
            $(this).toggle(found);
        });
    });

    $("#loadCourses").on("click", function() {
        yearFilter = $("#yearFilter").val();
        showOnlyUnconfigured = ($("#showFilter").val() === "unconfigured");
        $("#data").empty();
        $("#data").append('<div id="loading" style="padding:50px; text-align:center; color:#666;"><img src="https://cdnjs.cloudflare.com/ajax/libs/galleriffic/2.0.1/css/loader.gif" style="width:32px;"><br>Cargando...</div>');
        prevValues = {};
        getCourse();
    });
    
    $("#sendButton").on("click", function () {
        getNewValues();
    });
    
    $(document).on("click", "h1#closeResult" , function() {
        $("#resultMessage").remove();
    });
    
    $(document).on("click", "i.material-icons.undo-btn" , function() {
        var id = $(this).attr('data-courseid');
        var tr = $(this).parent().parent();
        $(tr).find('select.acuity-select').val(prevValues[id]['acuityID']);
        $(tr).find('input[type=number]').val(prevValues[id]['classNumber']);
        var checked = (prevValues[id]['isFundae'] === "t");
        $(tr).find('input[type=checkbox]').prop('checked', checked);
        $(tr).find('select.tipo-clase-select').val(prevValues[id]['tipoClase']);
    });

    // === HANDLERS PARA EL MODAL ===
    
    // Abrir modal al hacer clic en "+ Nuevo"
    $(document).on("click", ".btn-new-acuity", function() {
        var courseId = $(this).attr('data-courseid');
        var courseName = $(this).attr('data-coursename');
        
        $("#newAcuityCourseId").val(courseId);
        $("#newAcuityCourseLabel").text(courseName);
        $("#newAcuityId").val('');
        $("#newAcuityName").val('');
        $("#newAcuityError").hide();
        $("#newAcuityModal").css("display", "flex");
        $("#newAcuityId").focus();
    });
    
    // Cerrar modal
    $("#closeNewAcuityModal, #cancelNewAcuity").on("click", function() {
        $("#newAcuityModal").hide();
    });
    
    // Cerrar modal al hacer clic fuera
    $("#newAcuityModal").on("click", function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
    
    // Cerrar modal con ESC
    $(document).on("keydown", function(e) {
        if (e.key === "Escape") {
            $("#newAcuityModal").hide();
        }
    });
    
    // Guardar nuevo Acuity Type
    $("#saveNewAcuity").on("click", function() {
        var acuityId = $("#newAcuityId").val().trim();
        var acuityName = $("#newAcuityName").val().trim();
        var courseId = $("#newAcuityCourseId").val();
        
        // Validaciones
        if (!acuityId) {
            $("#newAcuityError").text("El Acuity ID es obligatorio").show();
            $("#newAcuityId").focus();
            return;
        }
        if (!/^\d{6,10}$/.test(acuityId)) {
            $("#newAcuityError").text("El Acuity ID debe ser un número de 6-10 dígitos").show();
            $("#newAcuityId").focus();
            return;
        }
        if (!acuityName) {
            $("#newAcuityError").text("El nombre es obligatorio (pega el de Acuity)").show();
            $("#newAcuityName").focus();
            return;
        }
        
        // Verificar si ya existe este acuityId
        var exists = false;
        $.each(acuityTypes, function(k, v) {
            if (v['acuityid'] === acuityId) {
                exists = true;
                return false;
            }
        });
        
        if (exists) {
            // Ya existe, solo asignarlo al dropdown
            $("tr#" + courseId + " select.acuity-select").val(acuityId);
            $("tr#" + courseId).css("background", "#e3f2fd");
            $("#newAcuityModal").hide();
            showMessage("ℹ️ Este Acuity ID ya existía - se ha asignado al curso", "#2196F3");
            return;
        }
        
        // Crear nuevo en own_acuitytypes
        var sqlInsert = "INSERT INTO own_acuitytypes (acuityid, acuitytype) VALUES (" + acuityId + ", '" + acuityName.replace(/'/g, "''") + "')";
        
        $("#saveNewAcuity").prop("disabled", true).text("Guardando...");
        
        $.ajax({
            url: './askddbb.php',
            data: { type: "set", sql: sqlInsert },
            type: "POST",
            success: function(result) {
                var res = $.parseJSON(result);
                if (res === true) {
                    // Añadir al array local
                    acuityTypes.push({ 'acuityid': acuityId, 'acuitytype': acuityName });
                    
                    // Añadir opción a TODOS los dropdowns
                    var newOption = '<option value="' + acuityId + '">' + acuityName + '</option>';
                    $("select.acuity-select").each(function() {
                        $(this).append(newOption);
                    });
                    
                    // Seleccionar en el curso actual
                    $("tr#" + courseId + " select.acuity-select").val(acuityId);
                    
                    // Marcar fila como modificada
                    $("tr#" + courseId).css("background", "#e3f2fd");
                    
                    $("#newAcuityModal").hide();
                    showMessage("✓ Acuity Type creado y asignado. Recuerda GUARDAR CAMBIOS.", "#4CAF50");
                } else {
                    // SEC-8: mostrar el error real en vez de un mensaje generico.
                    console.error("askddbb.php respondio con error:", res);
                    $("#newAcuityError").text(typeof res === "string" ? res.replace(/<br\s*\/?>/gi, " ") : "Error al guardar en la base de datos").show();
                }
            },
            error: function(xhr, status, error) {
                $("#newAcuityError").text("Error de conexión: " + error).show();
            },
            complete: function() {
                $("#saveNewAcuity").prop("disabled", false).text("✓ Crear y Asignar");
            }
        });
    });
});

function showMessage(msg, bg) {
    $("#resultMessage").remove();
    $("<div id='resultMessage' style='position:fixed; top:0; left:0; right:0; background:"+bg+"; color:white; padding:15px 30px; font-size:16px; z-index:9999; text-align:center;'>"+msg+"</div>").prependTo("body");
    setTimeout(function(){ $("#resultMessage").fadeOut(); }, 4000);
}

function getCourse(){
    var yearCondition = "";
    if (yearFilter !== "ALL") {
        var years = yearFilter.split(",");
        var conditions = years.map(function(y) { return "mdl_course_categories.name LIKE '" + y + "%'"; });
        yearCondition = " AND (" + conditions.join(" OR ") + ")";
    }
    
    var unconfiguredCondition = "";
    if (showOnlyUnconfigured) {
        unconfiguredCondition = " AND (own_acuity_course.classnmbr IS NULL OR own_acuity_course.classnmbr = 0)";
    }
    
    sqlString = "SELECT mdl_course.category as companyid, mdl_course_categories.name as company, mdl_course.id as courseid, mdl_course.fullname as coursename, own_acuity_course.acuityid, own_acuity_course.isfundae, own_acuity_course.classnmbr, own_acuity_course.tipo_clase FROM mdl_course_categories LEFT JOIN mdl_course ON mdl_course_categories.id = mdl_course.category AND mdl_course.category > 0 LEFT JOIN own_acuity_course ON mdl_course.id = own_acuity_course.courseid WHERE 1=1" + yearCondition + unconfiguredCondition + " GROUP BY mdl_course.id ORDER BY category";
    
    $.ajax({
        url: './askddbb.php',
        data: { type: "ask", sql: sqlString},
        type: "POST",
        success: function(result){
            res = $.parseJSON(result);
            companies = [];
            var totalCount = 0;
            $.each(res, function(k,v){
                // SEC-8: una categoria sin cursos devuelve courseid/coursename a NULL
                // por el LEFT JOIN. Esa fila rompia el pintado (v.courseName is null).
                if (v['courseid'] === null || v['coursename'] === null) return;
                if (v['companyid'] in companies === false)
                    companies[v['companyid']] = v['company'];
                if (v['classnmbr'] === null) v['classnmbr'] = "";
                // SEC-8: sin normalizar, null (BD) != "" (desplegable vacio) y el
                // codigo creia que TODOS los cursos sin configurar estaban modificados.
                if (v['acuityid'] === null) v['acuityid'] = "";
                if (v['tipo_clase'] === null) v['tipo_clase'] = "GRUPAL";
                totalCount++;
                prevValues[v['courseid']] = ({
                    'companyID': v['companyid'], 
                    'companyName': v['company'], 
                    'courseID': v['courseid'], 
                    'courseName': v['coursename'], 
                    'acuityID' : v['acuityid'], 
                    'isFundae': v['isfundae'], 
                    'classNumber': v['classnmbr'],
                    'tipoClase': v['tipo_clase']
                });
            });
            $("#data").empty();
            var filterText = showOnlyUnconfigured ? "sin configurar" : "total";
            $("#statsInfo").html("<strong>" + totalCount + "</strong> cursos " + filterText);
            createCompanies(companies);
            getTypes();
        },
        error: function(xhr, ajaxOptions, thrownError) {
            console.log(xhr.status);
            console.log(thrownError);
        }
    });
}

function getTypes(){
    sqlString = "SELECT acuityid, acuitytype FROM own_acuitytypes ORDER BY acuitytype ASC";
    $.ajax({
        url: './askddbb.php',
        data: { type: "ask", sql: sqlString},
        type: "POST",
        success: function(result){
            res = $.parseJSON(result);
            $.each(res, function(key,val){
                $.each(val, function(k, v){
                    if ($.isNumeric(k)) delete res[key][k];
                });
            });
            acuityTypes = res;
            fillCompanies();
        },
        error: function(xhr, ajaxOptions, thrownError) {
            console.log(xhr.status);
            console.log(thrownError);
        }
    });
}

function createCompanies(companies){
    $.each(companies, function(k,v){
        if (v !== undefined)
            $("#data").append("<div id='" + k +"'><fieldset><legend>"+v+"</legend><table></table></fieldset></div>");
    });
}

function fillCompanies(){
    $.each(prevValues, function(k,v){
        var isUnconfigured = (v['classNumber'] === "" || v['acuityID'] === null);
        var rowStyle = isUnconfigured ? "background:#fff3e0;" : "";
        
        // Escapar comillas en nombres para los data attributes
        var courseNameEscaped = v['courseName'].replace(/"/g, '&quot;');
        
        html = "<tr id='"+v['courseID']+"' style='"+rowStyle+"'>" +
                "<td style='width:22%;'><span>"+v['courseName']+"</span></td>" +
                "<td style='width:32%;'>" +
                "<div style='display:flex; gap:5px; align-items:center;'>" +
                "<select class='acuity-select' style='flex:1;'><option value=''>-- Seleccionar --</option>";
        $.each(acuityTypes, function(key, val){
            if (val['acuityid'] === v['acuityID']){
                html += "<option value='" + val['acuityid'] + "' selected>" + val['acuitytype'] + "</option>";
            } else {
                html += "<option value='" + val['acuityid'] + "'>" + val['acuitytype'] + "</option>";
            }
        });
        html += "</select>" +
                "<button type='button' class='btn-new-acuity' data-courseid='"+v['courseID']+"' data-coursename=\""+courseNameEscaped+"\" style='background:#00bcd4; color:white; border:none; padding:6px 10px; border-radius:4px; cursor:pointer; font-size:12px; white-space:nowrap;' title='Crear nuevo Acuity Type'>+ Nuevo</button>" +
                "</div></td>" +
                "<td style='width:8%;'><input type='number' min='0' max='255' step='1' placeholder='Nº' value='"+v['classNumber']+"' style='width:55px;'></td>" +
                "<td style='width:8%;'><label style='font-size:12px;'>FUNDAE<input type='checkbox' value='t'" + (v['isFundae'] === "t" ? " checked" : "") + "></label></td>" +
                "<td style='width:10%;'><select class='tipo-clase-select' style='width:85px;'>" +
                "<option value='GRUPAL'" + (v['tipoClase'] === 'GRUPAL' ? " selected" : "") + ">Grupal</option>" +
                "<option value='1TO1'" + (v['tipoClase'] === '1TO1' ? " selected" : "") + ">1 to 1</option>" +
                "</select></td>" +
                "<td style='width:4%;'><i data-courseid='" + v['courseID'] + "' class='material-icons undo-btn' title='Deshacer'>undo</i></td>" +
                "</tr>";
        $("div#"+v['companyID']+">fieldset>table").append(html);
    });
}

function getNewValues(){
    newValues = {};
    $("div#data > div").each(function(){
        var table = $(this).find("table");
        $("tr", table).each(function(){
            var checkBox = $(this).find('input[type=checkbox]').is(':checked') ? 't' : 'f';
            newValues[$(this).attr('id')] = ({
                'acuityID': $(this).find('select.acuity-select').val(), 
                'classNumber': $(this).find('input[type=number]').val(), 
                'isFundae': checkBox,
                'tipoClase': $(this).find('select.tipo-clase-select').val()
            });
        });
    });
    compareValues();
}

function compareValues(){
    var toUpdate = [];
    $.each(prevValues, function (k,v) {
        if (!newValues[k]) return;
        if (prevValues[k]['acuityID'] != newValues[k]['acuityID'] || 
            prevValues[k]['classNumber'] != newValues[k]['classNumber'] || 
            prevValues[k]['isFundae'] != newValues[k]['isFundae'] ||
            prevValues[k]['tipoClase'] != newValues[k]['tipoClase']){
            toUpdate.push({
                "courseID": k, 
                "acuityID": newValues[k]['acuityID'], 
                'classNumber': newValues[k]['classNumber'],
                'isFundae': newValues[k]['isFundae'],
                'tipoClase': newValues[k]['tipoClase']
            });
        }
    });
    saveChanges(toUpdate);
}

function saveChanges(toUpdate){
    if (toUpdate.length == 0){
        saveChangesResults(2);
        return;
    }
    // SEC-8: own_acuity_course.acuityid es NOT NULL. Una sola fila sin tipo de
    // clase tumbaba el INSERT entero, incluidas las filas correctas.
    toUpdate = toUpdate.filter(function(v){
        return v['acuityID'] !== "" && v['acuityID'] !== null && v['acuityID'] !== undefined;
    });
    if (toUpdate.length == 0){
        saveChangesResults(2);
        return;
    }
    var sqlValues = "";
    $.each(toUpdate, function (k, v) {
        var classNum = (v['classNumber'] == "") ? "NULL" : v['classNumber'];
        sqlValues += "(" + v['courseID'] + ", " + v['acuityID'] + ", " + classNum + ", '" + v['isFundae'] + "', '" + v['tipoClase'] + "', NOW())";
        if (k !== (toUpdate.length - 1)) sqlValues += ",";
    });
    sqlString = "INSERT INTO own_acuity_course (courseid, acuityid, classnmbr, isfundae, tipo_clase, lastmodified) VALUES " + sqlValues + " ON DUPLICATE KEY UPDATE acuityid=VALUES(acuityid), classnmbr=VALUES(classnmbr), isfundae=VALUES(isfundae), tipo_clase=VALUES(tipo_clase), lastmodified=VALUES(lastmodified)";
    $.ajax({
        url: './askddbb.php',
        data: {type: "set", sql: sqlString},
        type: "POST",
        success: function (result) {
            res = $.parseJSON(result);
            if (res === true) {
                saveChangesResults(1);
                $.each(toUpdate, function(k,v){
                    prevValues[v['courseID']]['acuityID'] = v['acuityID'];
                    prevValues[v['courseID']]['classNumber'] = v['classNumber'];
                    prevValues[v['courseID']]['isFundae'] = v['isFundae'];
                    prevValues[v['courseID']]['tipoClase'] = v['tipoClase'];
                    $("tr#" + v['courseID']).css("background", "#e8f5e9");
                });
            } else {
                // SEC-8: askddbb devuelve el texto del error como resultado.
                // Una cadena es truthy, asi que antes se pintaba como exito.
                console.error("askddbb.php respondio con error:", res);
                saveChangesResults(0);
            }
        },
        error: function (xhr, ajaxOptions, thrownError) {
            console.log(thrownError);
            saveChangesResults(0);
        }
    });
}

function saveChangesResults(res){
    $("#resultMessage").remove();
    var msg, bg;
    if (res === 1) { msg = "✓ Guardado correctamente"; bg = "#4CAF50"; }
    else if (res === 0) { msg = "✗ Error al guardar"; bg = "#f44336"; }
    else { msg = "⚠ Sin cambios"; bg = "#ff9800"; }
    $("<div id='resultMessage' style='position:fixed; top:0; left:0; right:0; background:"+bg+"; color:white; padding:15px 30px; font-size:16px; z-index:9999; text-align:center;'>"+msg+"</div>").prependTo("body");
    setTimeout(function(){ $("#resultMessage").fadeOut(); }, 3000);
}

var bookedDayLenght;
var acuityData = [];
var pastAcuityData = [];
var futureAcuityData = [];
$('document').ready(function() {
    $("#loading").dialog({
        dialogClass: "no-close",
        dialogClass: "noTitleStuff",
        draggable: false,
        resizable: false,
        height: 100,
        modal: true
    });
    userID = $("#hiddenUserID").val();
    userLevel = $("#hiddenUserLevel").val();
    courseID = $("#hiddenCourseID").val();
    getMoodleData(userLevel, userID, courseID);

    $(document).on("click", ".dialogIframe", function(){
        $("#" + $(this).attr('data-value')).dialog("open");
    });
});
function getMoodleData(userLevel, userID, courseID) {
    if (userLevel === "admin"){
        sqlString = "SELECT acuityid, studentid FROM own_acuity WHERE courseid = " + courseID + " AND (iscancelled = 'f' OR isteached = 't')";
    } else if (userLevel === "teacher" || userLevel === "editingteacher" || userLevel === "coursecreator"){
        if (userID === 161){
            sqlString = "SELECT acuityid, studentid FROM own_acuity WHERE courseid = " + courseID + " AND (iscancelled = 'f' OR isteached = 't')";
        } else {
            sqlString = "SELECT acuityid, studentid FROM own_acuity WHERE teacherid = " + userID + " AND courseid = " + courseID + " AND (iscancelled = 'f' OR isteached = 't')";
        }
    } else if (userLevel === "student" || userLevel === "guest" || userLevel === "user" || userLevel === "frontpage"){
        sqlString = "SELECT acuityid, studentid FROM own_acuity WHERE studentid = " + userID + " AND courseid = " + courseID + " AND (iscancelled = 'f' OR isteached = 't')";
    }
    $.ajax({
        url: './askddbb.php',
        data: { type: "ask", sql: sqlString},
        type: "POST",
        success: function(result){
            res = $.parseJSON(result);
            bookedDayLenght = res.length;
            if (bookedDayLenght === 0){
                $("#content").html("<div class='row'><div class='col-sm-2'></div><div class='col-sm-8'><h3>Lo sentimos, no hay informaci&oacute;n que mostrar para este curso.</h3></div><div class='col-sm-12'></div></div>");
                $("#loading").dialog("close");
            } else {
                $.each(res, function (key, value) {
                    if (!$("#content #" + value['studentid']).length)
                    $("#content").append("<h3 id='" + value['studentid'] + "' class='studentName'></h3><div id='" + value['studentid'] + "' class='trackAccordion'><h3 class='hide'>Eventos pasados</h3><div id='past'><div class='noData'>Todav&iacute;a no existe eventos pasados.</div></div><h3 class='hide'>Eventos futuros</h3><div id='future'>No hay eventos futuros. Cuando reserve alguna clase le aparecer&aacute; aqu&iacute;.<div class='noData'></div></div></div>");
                    getAcuityData(value['acuityid'], value['studentid']);
                });
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            console.log(xhr.status);
            console.log(thrownError);
        }
    });
}
function getAcuityData(acuityid, studentid){
    apiurl = "appointments/" + acuityid;
   $.ajax({
        url: './acuityapi.php',
        data: { url: apiurl, data: ""},
        type: "POST",
        success: function(result){
            var res = $.parseJSON(result);
            keys = ["id", "datetime", "type", "duration", "calendar", "firstName", "lastName", "canceled", "location"];
            $.each(res, function(key, value){
                if ($.inArray(key, keys) === -1){
                    delete res[key];
                }
            });
            $("#content h3#" + studentid + ".studentName").html(res['firstName'] + " " + res['lastName']);
            res['studentid'] = parseInt(studentid);
            acuityData.push(res);
            orderAcuityData();
        },
        error: function(xhr, ajaxOptions, thrownError) {
            console.log(xhr.status);
            console.log(thrownError);
        }
    });

}
function orderAcuityData(){
    if (acuityData.length === bookedDayLenght){
        acuityData.sort(function(a, b){
            var keyA = new Date(a['datetime']),
                keyB = new Date(b['datetime']);
            if(keyA < keyB) return 1;
            if(keyA > keyB) return -1;
            return 0;
        });
		$cont = 1;
        $.each(acuityData, function (key, value) {
            date = new Date(value['datetime']);
            now = new Date();
            if (date.getTime() < now.getTime()){
				console.log("Pre: " + Date.now());
                setTimeout(createHTMLTemplate,60 * $cont,value,'past');
				$cont = $cont + 1;
            } else {
                createHTMLTemplate(value,'future');
            }
        });
    }
}
function createHTMLTemplate(acuity, time){
    if (acuity['type'] != undefined){
    date = new Date(acuity['datetime']);
    html = "<div id='"+ acuity['id'] +"' class='row'>" +
            "<div class='title'>" +
                "<h4>" + acuity['type'] + " - " + acuity['calendar'] + "</h4>" +
            "</div>" +
            "<div class='acuityInfo'>" +
                acuity['type'] + " (" + acuity['firstName'] + " " + acuity['lastName'] + ") " + date.getDate() + "-" + (date.getMonth() + 1) + "-" + date.getFullYear() + " a las " + date.getHours() + ":" + (date.getMinutes()<10?'0':'') + date.getMinutes() + " con duraci&oacute;n estimada en " + acuity['duration'] + " minutos." +
            "</div>" +
        "</div>";
    if (!$("#content #" + acuity['studentid'] + " #" + time + " .noData").length) {
        $("#content #" + acuity['studentid'] + " #" + time).append(html);
    } else {
        $("#content #" + acuity['studentid'] + " #" + time).html(html);
    }
    if(time === "past" && acuity['location'] !== "The Zoom meeting was cancelled.") {
		console.log("Template: " + Date.now());
       getZoomData(acuity['location'].split(": ")[2], acuity['id']);
    }
    $("#loading").dialog('close');

    if($("div#content").children().length <= 2){
        $("div#content").removeClass("trackAccordion");
    }

    var icons = {
        header: "ui-icon-circle-arrow-e",
        activeHeader: "ui-icon-circle-arrow-s"
    };
    $("div.trackAccordion").accordion({
        collapsible: true,
        active: false,
        heightStyle: "content",
        icons: icons
    });
    $("div.trackAccordion h3").removeClass('hide');
    }
}

function getZoomData(zoomID, acuityID){
    $.ajax({
        url: './own_MeetingDataGet.php?zoomID=' + zoomID,
        type: "GET",
        success: function(result){
            res = $.parseJSON(result);
            if (res['topic'] !== null) {
                startTime = new Date(res["start_time"]);
                html = "<div class='zoomInfo'>T&iacute;tulo de la sesi&oacute;n: " + res['topic'] + "<br>Hora de inicio de la sesi&oacute;n: " +startTime.getDate() + "-" + startTime.getMonth() + "-" + startTime.getFullYear() + " " + startTime.getHours() + ":" + startTime.getMinutes() + ":" + startTime.getSeconds() + ", duraci&oacute;n " + res['duration'] + " minutos.";
                if (typeof res['recording_files'] !== "undefined") {
                    $.each(res['recording_files'], function (k, v) {
                        if (res['recording_files'][k]['file_type'] === "MP4") {
                            fileType = "v&iacute;deo";
                        } else if (res['recording_files'][k]['file_type'] === "M4A") {
                            fileType = "audio";
                        } else if (res['recording_files'][k]['file_type'] === "CHAT") {
                            fileType = "texto";
                        }
                        html += "<br><a href='#' data-value='" + res['recording_files'][k]['file_type'] + zoomID + "' class='dialogIframe'>Ver archivo de " + fileType + ".</a> " + res['recording_files'][k]['file_size'] + " <a href='" + res['recording_files'][k]['download_url'] + "' target='_blank'>Descargar</a>.";
                        $("main").append("<div id='" + res['recording_files'][k]['file_type'] + zoomID + "' title='" + fileType.charAt(0).toUpperCase() + fileType.slice(1) + "'><iframe src='" + res['recording_files'][k]['play_url'] + "' width='100%' height='100%' frameborder='0' scrolling='no' style='min-width: 95%;height:100%;'></iframe></div>");
                        if ($(window).height() < $(window).width()) {
                            h = $(window).height();
                            if (h > 700) h = 700;
                        } else {
                            h = $(window).width() * 0.68;
                        }
                        $("#" + res['recording_files'][k]['file_type'] + zoomID).dialog({
                            draggable: false,
                            resizable: false,
                            modal: true,
                            width: h * 1.47,
                            height: h
                        });
                        $("#" + res['recording_files'][k]['file_type'] + zoomID).dialog("close");
                        $("#" + res['recording_files'][k]['file_type'] + zoomID).on('dialogclose', function(event) {
                            $("#" + res['recording_files'][k]['file_type'] + zoomID).html($("#" + res['recording_files'][k]['file_type'] + zoomID).html());
                        });
                    });
                }
                html += "</div>";
                $("div#" + acuityID).append(html);
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            console.log(xhr.status);
            console.log(thrownError);
        }
    });
}
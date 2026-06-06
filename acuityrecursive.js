/* Variables publicas */
var firstDay;
var shownDates = new Array(0);
var dayInfo = {};
var acuityid;
var teacherid;
var daycount = 0;
var isnext = true;
var selectedDays = {};
var week = new Array();
var weekInfo = {};
var classnmbr;

$('document').ready(function(){
	/* Valores iniciales */
	$("#seltimezone").timezones();
	$("#seltimezone").val(Intl.DateTimeFormat().resolvedOptions().timeZone);
	timezone = $("#seltimezone").val();
	
	$("#seltimezone").change(function() {
		timezone = $("#seltimezone").val();
		if (Object.keys(dayInfo).length > 0){
			$("#loading").dialog("open");
			d = new Date(dayInfo[0].date);
			emptyvars();
			getdays(d, false);
		}
	});
	$("#loading").dialog({
		dialogClass: "no-close",
		dialogClass: "noTitleStuff",
		draggable: false,
		resizable: false,
		height: 100,
		modal: true
	});
	$("#editday").dialog({
		draggable: false,
		resizable: false,
		modal: true,
		width: 520
	});
	$("#mssg").dialog({
		draggable: false,
		resizable: false,
		modal: true,
		width: 460
	});
	$("#loading").dialog("close");
	$("#editday").dialog("close");
	$("#mssg").dialog("close");
	
	$("#editedday").selectmenu({
		width: 210
	});
	$("#editeddayhour").selectmenu({
		width: 100
	});
	
	extrainfo = jQuery.parseJSON($("#extrainfo").attr('data-value'));
	
	/* Cuando un boton de clase teacher (los distintos calendarios que tiene Acuity) es pulsado muestra la 
	pantalla de loading, cambia las clases de los botones y pide los datos de horarios para el calendario */
	$('.teacher-group .teacher').click(function(){
		$("#profesores").css('border-bottom', 'lightgrey 1px solid');
		$("#dayhour").addClass('hide');
		$("#repetitions").addClass('hide');
		$("#confirmdays").addClass('hide');
		$("#loading").dialog("open");
		$(this).parent().find('.teacher').removeClass('active');
		$(this).addClass('active');
		acuityid = $('div.teacher-group').attr('data-value');
		teacherid = $(this).attr('data-value');
		var d = new Date();
		d.setHours(d.getHours() + 36);
		if (d.getHours() >= 20){
			d.setHours(d.getHours() + (24 - d.getHours()));
		}
		emptyvars();
		getdays(d, isnext);
	});
	/* Cuando una hora es pulsada muestra el apartado repeticiones y nos lleva a el */
	$(document).on("click", ".hourtable span" , function() {
		getrepetitions();
		$("#classnmbr").val($("#classnmbr").attr('max'));
		classnmbr = $("#classnmbr").val();
		$(this).parent().parent().parent().find('.hourspan').removeClass('hourselected');
		$(this).addClass('hourselected');
		$("#repetitions").removeClass('hide');
		$("#confirmdays").addClass('hide');
		if( $("#repetitions").length ) {
			event.preventDefault();
			$('html, body').stop().animate({
				scrollTop: $("#repetitions").offset().top
			}, 1000);
		}
	});
	/* Boton de siguientes dias, nos carga los siguientes 5 posibles dias */
	$('#moredays span').on('click', function(){
		date = new Date($('#moredays').attr('data-value'));
		if (date) {
			$("#loading").dialog('open');
			$("#lessdays span").removeClass('hide');
			emptyvars();
			getdays(date, isnext);
		}
	});
	/* Boton de dias anteriores, nos carga los anteriores 5 posibles dias */
	$('#lessdays span').on('click', function(){
		date = new Date($('#lessdays').attr('data-value'));
		if (date) {
			$("#loading").dialog('open');
			emptyvars();
			isnext = false;
			getdays(date, isnext);
		}
	});
	/* Input de numero de clases, cuando se edita se comprueba que los valores sean validos */
	$(document).on("change", "#classnmbr" , function() {
		if (parseInt($(this).val()) < $(this).attr('min')){
			$(this).val($(this).attr('min'));
		} else if (parseInt($(this).val()) > $(this).attr('max')){
			$(this).val($(this).attr('max'));
		}
		classnmbr = $("#classnmbr").val();
	});
	/* Boton para comprobar si las fechas y horas estan disponibles */
	$(document).on("click", "button#checktimes" , function() {
		$("#loading").dialog('open');
		var data = $("#writedays table").find('.hourselected').attr('data-value');
		var daydata = jQuery.parseJSON(data);
		selectedDays = [];
		var dates = {};
		dates[0] = daydata;
		for (i = 1; i < classnmbr; i++){
			d = new Date(dates[i-1].date);
			d.setDate(d.getDate() + 7);
			date = d.getFullYear() + "-" + (d.getMonth() + 1) + "-" + d.getDate();
			dates[i] = {"date": date, "hour": daydata.hour, "calendar": daydata.calendar};
		}
		apiurl = 'availability/check-times';
		daycount = 0;
		for (k = 0; k < Object.keys(dates).length; k++){
			if (dates[k].calendar != "any"){
				apidata = '{"datetime": "' + dates[k].date + 'T' + dates[k].hour + '", "appointmentTypeID": "' + acuityid + '", "calendarID": "' + dates[k].calendar + '"}';
			} else {
				apidata = '{"datetime": "' + dates[k].date + 'T' + dates[k].hour + '", "appointmentTypeID": "' + acuityid + '"}';
			}
			(function(key){
				$.ajax({
					url: './acuityapi.php',
					data: { url: apiurl, data: apidata},
					type: "POST",
					success: function(result){
						var res = $.parseJSON(result);
						selectedDays[key] = {"calendarID": res.calendarID, "date": res.datetime.split("T")[0], "time": res.datetime.split("T")[1], "disponible": res.valid};
						daycount++;
						if (daycount >= Object.keys(dates).length){
							showselecteddays();
						}
					},
					error: function(xhr, ajaxOptions, thrownError) {
						console.log(xhr.status);
						console.log(thrownError);
					}
				});
			})(k);
		}
	});
	/* Boton para editar la hora y dia que se nos ofrece */
	$(document).on("click", "i.edit" , function() {
		if (!$(this).parent().parent().hasClass("deleted")){
			theday = new Date(selectedDays[$(this).attr('data-value')].date);
			selday = $(this).attr('data-value');
			$("#editday").attr('data-value', $(this).attr('data-value'));
			theday.setDate(theday.getDate() - theday.getDay() + 1);
			theday.setHours(theday.getHours() + 8);
			$("#loading").dialog("open");
			getweek(theday, selday);
		}
	});
	/* Boton para borrar el dia que se nos ofrece */
	$(document).on("click", "i.del" , function() {
		if (!$(this).parent().parent().hasClass("deleted")){
			$(this).parent().parent().addClass('deleted');
			$(this).parent().parent().append("<i class='material-icons restore' data-value='" + $(this).attr('data-value') + "'>restore_from_trash</i>");
			selectedDays[$(this).attr('data-value')].disponible = false;
		}
	});
	/* Boton para restaurar el dia borrado */
	$(document).on("click", "i.restore", function(){
		$(this).parent().removeClass('deleted');
		$(this).parent().html($("#selectedday" + $(this).attr('data-value')));
		selectedDays[$(this).attr('data-value')].disponible = true;
	});
	/* Cuando el select de los dias se cambia actualizamos las horas a las del dia seleccionado */
	$("#editedday").selectmenu({
		change: function( event, ui ) {
			$("#editedday").attr('data-value', weekInfo[$("#editedday").val()].date);
			$("#editeddayhour").empty().append("<option value=" + $("#editedday").val() + " selected>" + weekInfo[$("#editedday").val()].dayHour[0] + "</option>");
			$("#editeddayhour").attr('data-value', weekInfo[$("#editedday").val()].dayHour[0]);
			for(j = 1; j < Object.keys(weekInfo[$("#editedday").val()].dayHour).length; j++){
				$("#editeddayhour").append("<option value=" + j + ">" + weekInfo[$("#editedday").val()].dayHour[j] + "</option>");
			}
			$("#editeddayhour").selectmenu("refresh");
		}
	});
	/* Cuando el select de las horas se cambia actualizamos su data-value */
	$("#editeddayhour").selectmenu({
		change: function( event, ui ) {
			$("#editeddayhour").attr('data-value', weekInfo[$("#editedday").val()].dayHour[$(this).val()]);
		}
	});
	/* Boton para "guardar" el dia que hemos editado */
	$("#saveeditedhour").click(function(){
		d = $("#editday").attr('data-value');
		v = $("#editedday").val();
		selectedDays[d].date = $("#editedday").attr('data-value');
		selectedDays[d].time = $("#editeddayhour").attr('data-value');
		selectedDays[d].disponible = true;
		$("#selectedday" + d).html(weekInfo[v].dayName + " " + weekInfo[v].day + " de " + weekInfo[v].monthName + " del " + new Date(weekInfo[v].date).getFullYear() + " a las " + selectedDays[d].time + "<i class='material-icons del' data-value='" + d + "'>delete</i>&nbsp;<i class='material-icons edit' data-value='" + d + "'>edit</i>");
		$("#selectedday" + d).parent().removeClass('isnotvalid');
		$("#selectedday" + d).parent().addClass('isvalid');
		$("#editday").dialog('close');
	});
	/* Boton guardar appointments */
	$(document).on("click", "#saveappointments", function(){
		$("#loading").dialog("open");
		var saveday = {};
		c = 0;
		for (i = 0; i < Object.keys(selectedDays).length; i++){
			if (selectedDays[i].disponible){
				saveday[c] = selectedDays[i];
				c++;
			}
		}
		saveAppointments(saveday);
	});
	/* Si la pantalla es menor de 600px de ancho */
	if ($(window).width() < 600) {
		$("#editday").dialog({
			width: 280
		});
		$("#editday").dialog("close");
	}
});

/* Funcion para rellenar el div repetitions */
function getrepetitions(){
	var maxclass;
	var classdone;
	$.ajax({
		url: 'https://aula.tuspeaking.com/app/moodle/askddbb.php',
		data: {type: 'ask', sql: "SELECT COUNT(own_acuity.id) as cuenta, own_acuity_course.classnmbr FROM own_acuity, own_acuity_course WHERE own_acuity_course.courseid = " + extrainfo.courseID + " AND own_acuity.studentid = " + extrainfo.userID + " AND own_acuity.courseid = " + extrainfo.courseID + " AND (own_acuity.iscancelled = 'f' OR own_acuity.isteached = 't')"},
		type: "POST",
		success: function(result){
			res = $.parseJSON(result);
			maxclass = res[0].classnmbr;
			classdone = res[0].cuenta;
			printrepetitions(maxclass, classdone);
		},
		error: function(xhr, ajaxOptions, thrownError) {
			console.log(xhr.status);
			console.log(thrownError);
		}
	});	
}
function printrepetitions(maxclass, classdone){
	resta = maxclass - classdone;
	html = "<p>Tienes previstas un total de " + maxclass + " clases.<br>"
		+ "Has reservado un total de " + classdone + " clases.<br>";
	if (resta > 1){
		html += "Te quedan un total de " + resta + " clases por reservar.";
	} else if (resta == 1){
		html += "Te queda 1 clase por reservar.";
	} else if (resta <= 0){
		html += "No te quedan clases por reservar.";
	}
	html += "<br>¿Cuántas clases quieres reservar? <input id='classnmbr' type='number' min='0' max ='" + resta + "' value='" + resta + "'>"
		+ "<br><button type='button' id='checktimes'>Comprobar fechas</button>";
	$("#repetitions").html(html);
	classnmbr = resta;
	if (resta <= 0){
		$("button#checktimes").attr('disabled', 'true');
	}
}

/* Funcion para poner las variables a cero. Sirve cuando se ha cargado la informacion de un calendario y se 
quiere cambiar a otro o para mostrar otros dias */
function emptyvars(){
	shownDates = [];
	dayInfo = []
	daycount = 0;
	isnext = true;
	selectedDays = [];
}
/* Funcion para buscar los dias a mostrar. Le pasamos una variable de fecha a partir de la cual buscar los datos */
function getdays(d, isnext){
	if (teacherid != "any"){
		apiurl = 'availability/dates?month=' + d.getFullYear() + "-" + (d.getMonth() + 1) + '&appointmentTypeID=' + acuityid + '&calendarID=' + teacherid;
	} else {
		apiurl = 'availability/dates?month=' + d.getFullYear() + "-" + (d.getMonth() + 1) + '&appointmentTypeID=' + acuityid;
	}
	$.ajax({
		url: './acuityapi.php',
		data: { url: apiurl},
		type: "POST",
		success: function(result){
			var res = $.parseJSON(result);
			if (isnext){
				for (k = 0; k < Object.keys(res).length; k++){
					var da = new Date(res[k]['date']);
					da.setHours(da.getHours() + 8);
					if (da.getTime() >= d.getTime()){
						shownDates.push(res[k]['date']);
					}
				}
				shownDates = shownDates.slice(0,5);
			} else {
				for (k = Object.keys(res).length - 1; k >= 0; k--){
					var da = new Date(res[k]['date']);
					da.setHours(da.getHours() + 8);
					if (da.getTime() < d.getTime()){
						shownDates.push(res[k]['date']);
					}
					if (Object.keys(shownDates).length >= 5){
						temparray = shownDates;
						shownDates = [];
						var len = temparray.length;
						var ind = 0;
						while( len -- ) {
							if( temparray[len] !== undefined ) {
								shownDates[ind] = temparray[len];
							}
							ind++;
						}
						break;
					}
				}
			}
			checkdays();
		},
		error: function(xhr, ajaxOptions, thrownError) {
			console.log(xhr.status);
			console.log(thrownError);
		}
	});

}
/* Function para comprobar si el array de dias a mostrar tiene los valores necesarios */
function checkdays(){
	if (shownDates.length > 5){
		shownDates = shownDates.slice(0,5);
		checkdays();
	} else if (shownDates.length == 5){
		getdayinfo();
	} else {
		if (isnext){
			d = new Date(shownDates[shownDates.length - 1]);
			d.setDate(d.getDate() + 1);
		} else {
			d = new Date(shownDates[shownDates.length - 1]);
			d.setDate(1);
			d.setHours(-12);
		}
		getdays(new Date(d), isnext);
	}
}
/* Funcion para añadir la informacion necesaria para cada dia a mostrar */
function getdayinfo(){
	for (k = 0; k < Object.keys(shownDates).length; k++){
		var d = new Date(shownDates[k]);
		dayInfo[k] = {};
		dayInfo[k].date = shownDates[k];
		dayInfo[k].dayName = getDayName(d.getDay());
		dayInfo[k].monthName = getMonthName(d.getMonth());
		dayInfo[k].day = d.getDate();
	}
	getdayhours();
}
/* Funcion para obtener las horas disponibles de cada dia a mostrar */
function getdayhours(){
	daycount = 0;
	for (k = 0; k < Object.keys(dayInfo).length; k++){
		if (teacherid != "any"){
			apiurl = 'availability/times?date=' + dayInfo[k].date + '&appointmentTypeID=' + acuityid + '&calendarID=' + teacherid + '&timezone=' + timezone;
		} else {
			apiurl = 'availability/times?date=' + dayInfo[k].date + '&appointmentTypeID=' + acuityid + '&timezone=' + timezone;
		}
		(function(key){
			$.ajax({
				url: './acuityapi.php',
				data: { url: apiurl},
				type: "POST",
				success: function(result){
					var res = $.parseJSON(result);
					var hours = {};
					for (j = 0; j < Object.keys(res).length; j++){
						var d = new Date(res[j]['time']);
						h = d.getHours();
						m = (d.getMinutes()<10?'0':'') + d.getMinutes();
						hours[j] = h + ":" + m;
					}
					dayInfo[key].dayHour = hours;
					daycount++;
					if (daycount >= 5){
						printresult();
					}
				},
				error: function(xhr, ajaxOptions, thrownError) {
					console.log(xhr.status);
					console.log(thrownError);
				}
			});
		})(k);
	}
}
/* Funcion para dibujar los dias a mostrar en HTML */
function printresult(){
	d = new Date(shownDates[shownDates.length - 1]);
	d.setDate(d.getDate() + 1);
	$("#moredays").attr('data-value', d);
	d = new Date(shownDates[0]);
	$("#lessdays").attr('data-value', d);
	
	var dayhtml = "<table class='center'>";
	var dayheader = "<thead><tr>";
	var trs = Array();
	for (k = 0; k < Object.keys(dayInfo).length; k++){
		dayheader += "<th>" + dayInfo[k].dayName + "<br><span>" + dayInfo[k].day + " " + dayInfo[k].monthName + "</span></th>";
		for (j = 0; j < Object.keys(dayInfo[k].dayHour).length; j++){
			if (!(j in trs)){
				trs[j] = Array();
			}
			var hourinfo = '{"date": "' + dayInfo[k].date + '", "hour": "' + dayInfo[k].dayHour[j] + '", "calendar": "' + teacherid + '"}';
			trs[j][k] = "<td class='hourtable'><span class='hourspan' data-value='" + hourinfo + "'>" + dayInfo[k].dayHour[j] + "</span></td>";
		}
	}
	for (j = 0; j < Object.keys(trs).length; j++){
		for (i = 0; i < 5; i++){
			if (typeof trs[j][i] != 'string'){
				trs[j][i] = "<td></td>";
			}
		}
	}
	dayheader +="</tr></thead>";
	var daybody = "<tbody>";
	for (k = 0; k < Object.keys(trs).length; k++){
		daybody += "<tr>";
		for (j = 0; j < Object.keys(trs[k]).length; j++){
			daybody +=  trs[k][j];
		}
		daybody += "</tr>";
	}
	daybody += "</tbody>";
	dayhtml += dayheader + daybody + "</table>";
	if (firstDay === undefined){
		firstDay = shownDates[0];
	}
	if (shownDates[0] == firstDay){
		$("#lessdays span").addClass('hide');
	}
	$("#writedays").html(dayhtml);
	$("#loading").dialog("close");
	$('#dayhour').removeClass('hide');
	$("#profesores").css('border-bottom', '0px');
}
/* Funcion para mostrar los dias seleccionados */
function showselecteddays(){
	$("#repetitions").addClass('hide');
	$("#confirmdays").removeClass('hide');
	$("#loading").dialog('close');
	var html = "";
	for (i = 0; i < Object.keys(selectedDays).length; i++){
		var d = new Date(selectedDays[i].date);

		html += "<div class='selecteddays ";
		if (selectedDays[i].disponible){
			html += "isvalid";
		} else {
			html += "isnotvalid";
		}
		html += "'><div id='selectedday" + i + "' class='lesssized'>" + getDayName(d.getDay()) + " " + d.getDate() + " de " + getMonthName(d.getMonth()) + " del " + d.getFullYear() + " a las " + selectedDays[i].time + "&nbsp;<i class='material-icons del' data-value='" + i + "'>delete</i>&nbsp;<i class='material-icons edit' data-value='" + i + "'>edit</i></div></div>";
	}
	html += "<div style='text-align:center; padding-top:10px;'><button type='button' id='saveappointments'>Guardar horas</button></div>";
	$("#confirmdays").html(html);
	if( $("#confirmdays").length ) {
		event.preventDefault();
		$('html, body').stop().animate({
			scrollTop: $("#confirmdays").offset().top
		}, 1000);
	}
}
/* Funcion que devuelve el nombre del dia que le pasamos como parametro */
function getDayName(day){
	var days = ['Domingo', 'Lunes', 'Martes', 'Mi&eacute;rcoles', 'Jueves', 'Viernes', 'S&aacute;bado'];
	return days[day];
}
/* Funcion que devuelve el nombre del mes que le pasamos como parametro */
function getMonthName(month){
	var months = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
	return months[month];
}

/*** Funciones para poder editar las fechas que nos ofrece la aplicacion ***/
function getweek(d, selday){
	d = new Date(d);
	if (teacherid != "any"){
		apiurl = 'availability/dates?month=' + d.getFullYear() + "-" + (d.getMonth() + 1) + '&appointmentTypeID=' + acuityid + '&calendarID=' + teacherid;
	} else {
		apiurl = 'availability/dates?month=' + d.getFullYear() + "-" + (d.getMonth() + 1) + '&appointmentTypeID=' + acuityid;
	}
	d = new Date();
	d.setHours(d.getHours() + 36);
	$.ajax({
		url: './acuityapi.php',
		data: { url: apiurl},
		type: "POST",
		success: function(result){
			var res = $.parseJSON(result);
			if (week.length == 5){
				week = [];
			}
			for (k = 0; k < Object.keys(res).length; k++){
				var da = new Date(res[k]['date']);
				da.setHours(da.getHours() + 8);
				if (da.getTime() >= d.getTime()){
					week.push(res[k]['date']);
				}
			}
			week = week.slice(0,5);
			checkweek(week, selday);
		},
		error: function(xhr, ajaxOptions, thrownError) {
			console.log(xhr.status);
			console.log(thrownError);
		}
	});

}
function checkweek(week, selday){
	if (week.length > 5){
		week = week.slice(0,5);
		checkweek(week, selday);
	} else if (week.length == 5){
		getweekinfo(selday);
	} else {
		d = new Date(week[week.length - 1]);
		d.setDate(d.getDate() + 1);
		getweek(d, selday);
	}
}
function getweekinfo(selday){
	weekInfo = {};
	for (k = 0; k < Object.keys(week).length; k++){
		var d = new Date(week[k]);
		weekInfo[k] = {};
		weekInfo[k].date = week[k];
		weekInfo[k].dayName = getDayName(d.getDay());
		weekInfo[k].monthName = getMonthName(d.getMonth());
		weekInfo[k].day = d.getDate();
	}
	getweekhours(selday);
}
function getweekhours(selday){
	daycount = 0;
	for (k = 0; k < Object.keys(weekInfo).length; k++){
		if (teacherid != "any"){
			apiurl = 'availability/times?date=' + weekInfo[k].date + '&appointmentTypeID=' + acuityid + '&calendarID=' + teacherid + '&timezone=' + timezone;
		} else {
			apiurl = 'availability/times?date=' + weekInfo[k].date + '&appointmentTypeID=' + acuityid + '&timezone=' + timezone;
		}
		(function(key){
			$.ajax({
				url: './acuityapi.php',
				data: { url: apiurl},
				type: "POST",
				success: function(result){
					var res = $.parseJSON(result);
					var hours = {};
					for (j = 0; j < Object.keys(res).length; j++){
						var d = new Date(res[j]['time']);
						h = d.getHours();
						m = (d.getMinutes()<10?'0':'') + d.getMinutes();
						hours[j] = h + ":" + m;
					}
					weekInfo[key].dayHour = hours;
					daycount++;
					if (daycount >= 5){
						printweek(selday);
					}
				},
				error: function(xhr, ajaxOptions, thrownError) {
					console.log(xhr.status);
					console.log(thrownError);
				}
			});
		})(k);
	}
}
function printweek(selday){
	seldate = new Date(selectedDays[selday].date);
	$("#loading").dialog("close");	
	$("#editedday").empty().append("<option value="+0+" selected>" + getDayName(new Date(weekInfo[0].date).getDay()) + " " + weekInfo[0].date + "</option>");
	$("#editedday").attr('data-value', weekInfo[0].date)
	$("#editeddayhour").empty().append("<option value="+0+" selected>" + weekInfo[0].dayHour[0] + "</option>");
	$("#editeddayhour").attr('data-value', weekInfo[0].dayHour[0])
	for(i = 1; i < Object.keys(weekInfo).length; i++){
		d = new Date(weekInfo[i].date);
		if (d.getDate() == seldate.getDate()){
			$("#editedday").append("<option value=" + i + " selected>" + getDayName(new Date(weekInfo[i].date).getDay()) + " " + weekInfo[i].date + "</option>");
		} else {
			$("#editedday").append("<option value=" + i + ">" + getDayName(new Date(weekInfo[i].date).getDay()) + " " + weekInfo[i].date + "</option>");
		}
	}
	for(j = 1; j < Object.keys(weekInfo[0].dayHour).length; j++){
		if(weekInfo[0].dayHour[j] == selectedDays[selday].time){
			$("#editeddayhour").append("<option value=" + j + " selected>" + weekInfo[0].dayHour[j] + "</option>");
		} else {
			$("#editeddayhour").append("<option value=" + j + ">" + weekInfo[0].dayHour[j] + "</option>");
		}
	}
	$("#editedday").selectmenu("refresh");
	$("#editeddayhour").selectmenu("refresh");
	$("#editday").dialog('open');
}
/*** Fin edicion ***/
/* Funcion para guardar los datos en Acuity */
function saveAppointments(days){
	var saveddays = {};
	for (i = 0; i < Object.keys(days).length; i++){
		apiurl = 'appointments?noEmail=true';
		if (teacherid){
			apidata = '{"datetime": "' + days[i].date + 'T' + days[i].time + '", "appointmentTypeID": "' + acuityid + '", "calendarID": "' + teacherid + '", "firstName": "' + extrainfo.firstName + '", "lastName": "' + extrainfo.lastName + '", "email": "' + extrainfo.email + '", "fields": [{"id": 5837866, "value": ' + extrainfo.userID + '}, {"id": 5915926, "value": ' + extrainfo.courseID + '}]}';
		} else {
			apidata = '{"datetime": "' + days[i].date + 'T' + days[i].time + '", "appointmentTypeID": "' + acuityid + '", "firstName": "' + extrainfo.firstName + '", "lastName": "' + extrainfo.lastName + '", "email": "' + extrainfo.email + '", "fields": [{"id": 5837866, "value": ' + extrainfo.userID + '}, {"id": 5915926, "value": ' + extrainfo.courseID + '}]}';
		}
		(function(key){
			$.ajax({
				url: './acuityapi.php',
				data: { url: apiurl, data: apidata},
				type: "POST",
				success: function(result){
					var res = $.parseJSON(result);
					saveddays[key] = {};
					saveddays[key] = {"appointmentTypeID": res.appointmentTypeID, "type": res.type, "calendarID": res.calendarID, "calendar": res.calendar, "confirmationPage": res.confirmationPage, "date": res.date, "datetime": res.datetime, "time": res.time, "duration": res.duration, "email": res.email, "location": res.location};
					if (Object.keys(saveddays).length >= Object.keys(days).length){
						$("#loading").dialog("close");
						sendmail(saveddays);
					}
				},
				error: function(xhr, ajaxOptions, thrownError) {
					console.log(xhr.status);
					console.log(thrownError);
				}
			});
		})(i);
	}
}
/* Funcion para enviar correo con los datos de la reserva */
function sendmail(saveddays){
	d = new Date(saveddays[0].date);
	mailsubject = "Nueva sesi\xF3n: " + saveddays[0].type + " (" + extrainfo.firstName + " " + extrainfo.lastName + ") " + getDayName(d.getDay()) + ", " + getMonthName(d.getMonth()) + " " + d.getDate() + ", " + d.getFullYear() + " " + saveddays[0].time + " CET (+" + (Object.keys(saveddays).length - 1) + " otros d\xEDas) con " + saveddays[0].calendar;
	
	mailcontent = '<html>'
		+'<head>'
		+'</head>'
		+'<body>'
			+'<div style="max-width: 500px; margin: auto; border: #efefef 1px solid;font-family: sans-serif;">'
				+'<div style="text-align: center;">'
					+'<img src="https://aula.tuspeaking.com/app/moodle/tuSpeaking.png" width="119" height="51" alt="tuSpeaking">'
				+'</div>'
				+'<div style="text-align: center; color:#999999; font-size: 30px; font-weight: bold;">'
					+'<p>Sesiones Programadas</p>'
				+'</div>'
				+'<div style="text-align: center;font-size: 21px;line-height: 25px;color: #333333;">'
					+'<p>para ' + extrainfo.lastName + ' ' + extrainfo.lastName + '</p>'
				+'</div>'
				+'<hr style="border: 1px solid #efefef; width: 90%;">'
				+'<div style="padding-top: 25px; font-size: 18px; line-height: 25px; margin: auto; width:90%; padding-left: 20px;">'
					+'<div style="text-align:left; color:#999999; width:15%; display:inline-block; vertical-align:top;">Tipo</div>'
					+'<div style="text-align:left; color:#333333; display:inline-block;">' + saveddays[0].type + ' (' + saveddays[0].calendar + ' )</div>'
					+'<div style="padding-top: 20px"></div>'
					+'<div style="text-align:left; color:#999999; width:15%; display:inline-block; vertical-align:top; padding-top:20px;">Cuándo</div>'
					+'<div style="text-align:left; color:#333333; display:inline-block; padding-left:5px; padding-top:20px; width:84%;">';
					for (i = 0; i < Object.keys(saveddays).length; i++){
						d = new Date(saveddays[i].date);
						mailcontent += '<div>' + getDayName(d.getDay()) + ', ' + d.getDate() + ' ' + getMonthName(d.getMonth()) + ', ' + d.getFullYear() + " " + saveddays[i].time + ' \xAD<a href="' + saveddays[i].confirmationPage +'"><img src="https://aula.tuspeaking.com/app/moodle/edit.png" alt="edit" width="20" heigth="20"></a><a href="' + saveddays[i].location.split(" ")[1] + '"><img src="https://d24cgw3uvb9a9h.cloudfront.net/zoom.ico" alt="zoom" width="20" height="20"></a></div>';
					}
					mailcontent += '(' + saveddays[0].duration + ' minutos)'
					+ '</div>'
					+'<div style="padding-top:20px; font-size:16px;color:#999999;">'
						+'Gracias, su sesión ha sido programada.'
					+'</div>'
				+'</div>'
			+'</div>'
		+'</body>'
		+'</html>';
		
	$.ajax({
		url: 'https://aula.tuspeaking.com/app/moodle/mailsender.php',
		data: {email: extrainfo.email, subject: mailsubject, content: mailcontent},
		type: "POST",
		success: function(result){
			$("#mssg").html("<div>Gracias por realizar su reserva.<br>Le hemos enviado un mensaje a " + extrainfo.email + " con el resumen de sus clases, podrá modificarlos desde ahí o pulsando en el botón \"Modificar/Cancelar Sesión\" de su calendario de Moodle.</div>");
			$("#profesores").css("border-bottom", "lightgrey 1px solid");
			event.preventDefault();
			$('html, body').stop().animate({
				scrollTop: $("#profesores").offset().top
			}, 0);
			emptyvars();
			$("#dayhour").addClass("hide");
			$("#repetitions").addClass("hide");
			$("#confirmdays").addClass("hide");
			$("#mssg").dialog("open");
		},
		error: function(xhr, ajaxOptions, thrownError) {
			console.log(xhr.status);
			console.log(thrownError);
		}
	});
}

<?php
/**
 * Vista: Diagnosticos recibidos del agente Python
 * Lista con filtros, detalle y recomendaciones IA
 */
?>
<section class="content-header">
    <h1>Diagnosticos <small>Reportes del agente de soporte</small></h1>
</section>

<section class="content">
    <!-- Estadisticas rapidas -->
    <div class="row" id="stats-row">
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-desktop"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total</span>
                    <span class="info-box-number" id="stat-total">0</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-red"><i class="fa fa-exclamation"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Criticos</span>
                    <span class="info-box-number" id="stat-criticos">0</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-yellow"><i class="fa fa-microchip"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Prom. CPU</span>
                    <span class="info-box-number" id="stat-cpu">0%</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-hdd-o"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Prom. Disco</span>
                    <span class="info-box-number" id="stat-disco">0%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla -->
    <div class="box box-info">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-stethoscope"></i> Diagnosticos Recibidos</h3>
        </div>
        <div class="box-body">
            <table id="tbl-diagnosticos" class="table table-bordered table-hover table-condensed" width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Equipo</th>
                        <th>S.O.</th>
                        <th>CPU</th>
                        <th>RAM</th>
                        <th>Disco</th>
                        <th>Urgencia</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>

<!-- Modal: Detalle Diagnostico -->
<div class="modal fade" id="modalDiagnostico" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-stethoscope"></i> Detalle del Diagnostico</h4>
            </div>
            <div class="modal-body" id="detalle-diag-body">
                <div class="text-center"><i class="fa fa-spinner fa-spin"></i> Cargando...</div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary btn-sm" id="btn-recomendaciones-ia"><i class="fa fa-magic"></i> Generar Recomendaciones IA</button>
                <button class="btn btn-success btn-sm" id="btn-crear-ticket-desde-diag"><i class="fa fa-ticket"></i> Crear Ticket</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
var tablaDiag;
var diagActualId = null;

$(document).ready(function() {
    tablaDiag = $("#tbl-diagnosticos").DataTable({
        ajax: { url: "" + AJAX + "DiagnosticoAjax.php?op=list", dataSrc: "aaData" },
        columns: [
            { data: "0" }, { data: "1" }, { data: "2" }, { data: "3" },
            { data: "4" }, { data: "5" }, { data: "6" }, { data: "7" },
            { data: "8" }, { data: "9" }
        ],
        language: { url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json" },
        order: [[1, "desc"]],
        responsive: true
    });

    cargarEstadisticasDiag();

    // Recomendaciones IA
    $("#btn-recomendaciones-ia").on("click", function() {
        if (!diagActualId) return;
        var btn = $(this);
        btn.prop("disabled", true).html('<i class="fa fa-spinner fa-spin"></i> Generando...');

        $.getJSON("" + AJAX + "DashboardAjax.php?op=recomendacionesIA&iddiagnostico=" + diagActualId, function(data) {
            btn.prop("disabled", false).html('<i class="fa fa-magic"></i> Generar Recomendaciones IA');

            if (data.recomendaciones) {
                var html = '<h4><i class="fa fa-magic"></i> Recomendaciones IA</h4><div class="well">';
                if (Array.isArray(data.recomendaciones)) {
                    html += '<table class="table table-condensed"><thead><tr><th>Accion</th><th>Prioridad</th><th>Tiempo est.</th><th>Repuesto?</th></tr></thead><tbody>';
                    data.recomendaciones.forEach(function(r) {
                        html += '<tr><td>' + r.accion + '</td><td><span class="label label-' + prioridadLabel(r.prioridad) + '">' + r.prioridad + '</span></td><td>' + r.tiempo_estimado_min + ' min</td><td>' + (r.requiere_repuesto ? 'Si' : 'No') + '</td></tr>';
                    });
                    html += '</tbody></table>';
                } else {
                    html += data.recomendaciones;
                }
                html += '</div>';
                $("#detalle-diag-body").append(html);
            }
        });
    });
});

function cargarEstadisticasDiag() {
    $.getJSON("" + AJAX + "DiagnosticoAjax.php?op=stats", function(s) {
        if (s) {
            $("#stat-total").text(s.total || 0);
            $("#stat-criticos").text(s.criticos || 0);
            $("#stat-cpu").text(parseFloat(s.avg_cpu || 0).toFixed(1) + "%");
            $("#stat-disco").text(parseFloat(s.avg_disco || 0).toFixed(1) + "%");
        }
    });
}

function verDiagnostico(id) {
    diagActualId = id;
    $("#modalDiagnostico").modal("show");

    $.getJSON("" + AJAX + "DiagnosticoAjax.php?op=get&id=" + id, function(d) {
        var html = '<div class="row">';

        // Info del sistema
        html += '<div class="col-md-6"><h4>Sistema</h4><table class="table table-condensed">';
        html += '<tr><th>Equipo:</th><td>' + (d.hostname || '') + '</td></tr>';
        html += '<tr><th>S.O.:</th><td>' + (d.sistema_operativo || '') + '</td></tr>';
        html += '<tr><th>CPU:</th><td>' + (d.cpu_modelo || '') + '</td></tr>';
        html += '<tr><th>Cliente:</th><td>' + (d.nombre_cliente || '') + ' ' + (d.cliente_registrado ? '(Registrado)' : '') + '</td></tr>';
        html += '<tr><th>Telefono:</th><td>' + (d.telefono_cliente || '-') + '</td></tr>';
        html += '<tr><th>Email:</th><td>' + (d.email_cliente || '-') + '</td></tr>';
        html += '</table></div>';

        // Metricas
        html += '<div class="col-md-6"><h4>Metricas</h4>';
        html += metricaBar('CPU', d.cpu_uso_porcentaje);
        html += metricaBar('RAM', d.ram_uso_porcentaje);
        html += metricaBar('Disco', d.disco_uso_porcentaje);
        html += '<p><strong>Temperatura:</strong> ' + (d.temperatura_cpu || 'N/A') + ' C</p>';
        html += '<p><strong>Procesos:</strong> ' + (d.procesos_activos || 'N/A') + '</p>';
        html += '</div></div>';

        // Problemas y recomendaciones
        var problemas = [];
        var recomendaciones = [];
        try { problemas = JSON.parse(d.problemas_detectados || '[]'); } catch(e) {}
        try { recomendaciones = JSON.parse(d.recomendaciones || '[]'); } catch(e) {}

        if (problemas.length > 0) {
            html += '<div class="row"><div class="col-md-12"><h4 class="text-danger"><i class="fa fa-exclamation-triangle"></i> Problemas Detectados</h4><ul class="list-group">';
            problemas.forEach(function(p) { html += '<li class="list-group-item list-group-item-danger">' + p + '</li>'; });
            html += '</ul></div></div>';
        }

        if (recomendaciones.length > 0) {
            html += '<div class="row"><div class="col-md-12"><h4 class="text-info"><i class="fa fa-lightbulb-o"></i> Recomendaciones</h4><ul class="list-group">';
            recomendaciones.forEach(function(r) { html += '<li class="list-group-item list-group-item-info">' + r + '</li>'; });
            html += '</ul></div></div>';
        }

        // Resumen IA
        if (d.resumen_ia) {
            html += '<div class="row"><div class="col-md-12"><h4><i class="fa fa-magic"></i> Resumen IA</h4>';
            html += '<div class="well bg-info" style="white-space:pre-wrap;">' + d.resumen_ia + '</div></div></div>';
        }

        // Boton crear ticket
        $("#btn-crear-ticket-desde-diag").off("click").on("click", function() {
            window.crearTicketDesdeDiagnostico(id, d);
            $("#modalDiagnostico").modal("hide");
        });

        $("#detalle-diag-body").html(html);
    });
}

function metricaBar(label, valor) {
    valor = parseFloat(valor || 0);
    var color = valor > 90 ? 'danger' : (valor > 75 ? 'warning' : (valor > 50 ? 'info' : 'success'));
    return '<p><strong>' + label + ':</strong> ' + valor.toFixed(1) + '%</p>'
        + '<div class="progress" style="height:15px;"><div class="progress-bar progress-bar-' + color + '" style="width:' + valor + '%"></div></div>';
}

function prioridadLabel(p) {
    var map = { baja: 'success', media: 'warning', alta: 'danger', critica: 'danger' };
    return map[p] || 'default';
}

// Funcion global para crear ticket desde diagnostico
window.crearTicketDesdeDiagnostico = function(iddiag, datos) {
    if (typeof nuevoTicket === 'function') {
        nuevoTicket();
        $("#titulo").val("Diagnostico: " + (datos.hostname || 'PC'));
        var desc = "Diagnostico automatico\nEquipo: " + (datos.hostname || '') + "\nCPU: " + (datos.cpu_uso_porcentaje || '?') + "%\nRAM: " + (datos.ram_uso_porcentaje || '?') + "%\nDisco: " + (datos.disco_uso_porcentaje || '?') + "%";
        $("#descripcion").val(desc);
        if (datos.idpersona) $("#idpersona").val(datos.idpersona).trigger("change");
    } else {
        alert("Navega a Tickets para crear el ticket.");
    }
};
</script>

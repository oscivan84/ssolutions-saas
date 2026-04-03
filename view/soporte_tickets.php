<?php
/**
 * Vista: Gestion de Tickets de Soporte
 * CRUD completo con DataTables, modales y cambios de estado
 */
?>
<section class="content-header">
    <h1>Tickets de Soporte <small>Gestion completa</small></h1>
</section>

<section class="content">
    <!-- Filtros rapidos -->
    <div class="row">
        <div class="col-md-12">
            <div class="btn-group" style="margin-bottom: 15px;">
                <button class="btn btn-default btn-sm" onclick="filtrarTickets('')"><i class="fa fa-list"></i> Todos</button>
                <button class="btn btn-primary btn-sm" onclick="filtrarTickets('abierto')"><i class="fa fa-folder-open"></i> Abiertos</button>
                <button class="btn btn-info btn-sm" onclick="filtrarTickets('en_diagnostico')"><i class="fa fa-search"></i> En Diagnostico</button>
                <button class="btn btn-warning btn-sm" onclick="filtrarTickets('en_proceso')"><i class="fa fa-cog fa-spin"></i> En Proceso</button>
                <button class="btn btn-default btn-sm" onclick="filtrarTickets('esperando_repuestos')"><i class="fa fa-clock-o"></i> Esperando Repuestos</button>
                <button class="btn btn-success btn-sm" onclick="filtrarTickets('finalizado')"><i class="fa fa-check"></i> Finalizados</button>
                <button class="btn btn-danger btn-sm" onclick="filtrarTickets('cancelado')"><i class="fa fa-times"></i> Cancelados</button>
            </div>
            <button class="btn btn-primary btn-sm pull-right" onclick="nuevoTicket()"><i class="fa fa-plus"></i> Nuevo Ticket</button>
        </div>
    </div>

    <!-- Tabla de tickets -->
    <div class="box box-primary">
        <div class="box-body">
            <table id="tbl-tickets" class="table table-bordered table-hover table-condensed" width="100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Codigo</th>
                        <th>Titulo</th>
                        <th>Cliente</th>
                        <th>Tecnico</th>
                        <th>Estado</th>
                        <th>Tipo</th>
                        <th>Costo Est.</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</section>

<!-- Modal: Nuevo/Editar Ticket -->
<div class="modal fade" id="modalTicket" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-ticket"></i> <span id="modalTicketTitulo">Nuevo Ticket</span></h4>
            </div>
            <form id="frmTicket">
                <div class="modal-body">
                    <input type="hidden" id="idticket" name="idticket">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Cliente *</label>
                                <select id="idpersona" name="idpersona" class="form-control select2" required>
                                    <option value="">Seleccione cliente...</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Tecnico asignado</label>
                                <select id="idusuario" name="idusuario" class="form-control select2">
                                    <option value="">Sin asignar</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Titulo *</label>
                        <input type="text" id="titulo" name="titulo" class="form-control" placeholder="Descripcion breve del problema" required>
                    </div>
                    <div class="form-group">
                        <label>Descripcion *</label>
                        <textarea id="descripcion" name="descripcion" class="form-control" rows="3" placeholder="Detalle del problema reportado" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Tipo de servicio</label>
                                <select id="tipo_servicio" name="tipo_servicio" class="form-control">
                                    <option value="remoto">Remoto</option>
                                    <option value="en_sitio">En sitio</option>
                                    <option value="taller">Taller</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Prioridad</label>
                                <select id="prioridad" name="prioridad" class="form-control">
                                    <option value="baja">Baja</option>
                                    <option value="media" selected>Media</option>
                                    <option value="alta">Alta</option>
                                    <option value="critica">Critica</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Costo estimado</label>
                                <input type="number" id="costo_estimado" name="costo_estimado" class="form-control" value="0" min="0" step="1000">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Guardar Ticket</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Ver Ticket Detalle -->
<div class="modal fade" id="modalVerTicket" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-eye"></i> Detalle del Ticket <span id="ver-codigo"></span></h4>
            </div>
            <div class="modal-body" id="detalle-ticket-body">
                <div class="text-center"><i class="fa fa-spinner fa-spin"></i> Cargando...</div>
            </div>
            <div class="modal-footer">
                <div class="btn-group">
                    <button class="btn btn-info btn-sm" onclick="cambiarEstadoTicket('en_diagnostico')"><i class="fa fa-search"></i> Diagnosticar</button>
                    <button class="btn btn-warning btn-sm" onclick="cambiarEstadoTicket('en_proceso')"><i class="fa fa-cog"></i> En Proceso</button>
                    <button class="btn btn-success btn-sm" onclick="cambiarEstadoTicket('finalizado')"><i class="fa fa-check"></i> Finalizar</button>
                    <button class="btn btn-danger btn-sm" onclick="cambiarEstadoTicket('cancelado')"><i class="fa fa-times"></i> Cancelar</button>
                </div>
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Registrar Mantenimiento -->
<div class="modal fade" id="modalMantenimiento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-wrench"></i> Registrar Mantenimiento</h4>
            </div>
            <form id="frmMantenimiento">
                <div class="modal-body">
                    <input type="hidden" id="mnt-idticket" name="idticket">
                    <div class="form-group">
                        <label>Tipo de accion</label>
                        <select id="mnt-tipo" name="tipo_accion" class="form-control" required>
                            <option value="diagnostico">Diagnostico</option>
                            <option value="reparacion">Reparacion</option>
                            <option value="limpieza">Limpieza</option>
                            <option value="instalacion">Instalacion</option>
                            <option value="actualizacion">Actualizacion</option>
                            <option value="reemplazo">Reemplazo</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Descripcion *</label>
                        <textarea id="mnt-descripcion" name="descripcion" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Duracion (minutos)</label>
                                <input type="number" id="mnt-duracion" name="duracion_minutos" class="form-control" value="30" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Costo mano de obra</label>
                                <input type="number" id="mnt-costo" name="costo" class="form-control" value="0" min="0" step="1000">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Repuestos usados (JSON)</label>
                        <textarea id="mnt-repuestos" name="repuestos_usados" class="form-control" rows="2" placeholder='[{"idarticulo": 1, "cantidad": 1, "precio": 5000}]'>[]</textarea>
                        <small class="text-muted">Formato: [{"idarticulo": ID, "cantidad": N, "precio": P}]</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning"><i class="fa fa-save"></i> Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var tablaTickets;
var ticketActualId = null;
var filtroEstado = '';

$(document).ready(function() {
    cargarTablaTickets();
    cargarClientes();
    cargarTecnicos();

    // Form: Nuevo ticket
    $("#frmTicket").on("submit", function(e) {
        e.preventDefault();
        guardarTicket();
    });

    // Form: Mantenimiento
    $("#frmMantenimiento").on("submit", function(e) {
        e.preventDefault();
        guardarMantenimiento();
    });
});

function cargarTablaTickets(estado) {
    var url = "../landingV2/ajax/TicketAjax.php?op=list";
    if (estado) url += "&estado=" + estado;

    if (tablaTickets) tablaTickets.destroy();

    tablaTickets = $("#tbl-tickets").DataTable({
        ajax: { url: url, dataSrc: "aaData" },
        columns: [
            { data: "0" }, { data: "1" }, { data: "2" }, { data: "3" },
            { data: "4" }, { data: "5" }, { data: "6" }, { data: "7" },
            { data: "8" }, { data: "9" }
        ],
        language: { url: "//cdn.datatables.net/plug-ins/1.10.25/i18n/Spanish.json" },
        order: [[8, "desc"]],
        responsive: true
    });
}

function filtrarTickets(estado) {
    filtroEstado = estado;
    cargarTablaTickets(estado);
}

function nuevoTicket() {
    $("#frmTicket")[0].reset();
    $("#idticket").val("");
    $("#modalTicketTitulo").text("Nuevo Ticket");
    $("#modalTicket").modal("show");
}

function guardarTicket() {
    $.ajax({
        url: "../landingV2/ajax/TicketAjax.php?op=SaveOrUpdate",
        type: "POST",
        data: $("#frmTicket").serialize(),
        success: function(resp) {
            try { resp = JSON.parse(resp); } catch(e) {}
            if (resp.codigo) {
                alert("Ticket creado: " + resp.codigo);
            } else {
                alert(resp.message || resp);
            }
            $("#modalTicket").modal("hide");
            cargarTablaTickets(filtroEstado);
        }
    });
}

function verTicket(id) {
    ticketActualId = id;
    $("#modalVerTicket").modal("show");

    $.getJSON("../landingV2/ajax/TicketAjax.php?op=get&id=" + id, function(t) {
        var html = '<div class="row">';
        html += '<div class="col-md-6">';
        html += '<table class="table table-condensed">';
        html += '<tr><th>Codigo:</th><td><strong>' + t.codigo_ticket + '</strong></td></tr>';
        html += '<tr><th>Cliente:</th><td>' + (t.cliente || 'N/A') + '</td></tr>';
        html += '<tr><th>Tecnico:</th><td>' + (t.tecnico || '<em>Sin asignar</em>') + '</td></tr>';
        html += '<tr><th>Estado:</th><td><span class="label label-' + estadoLabel(t.estado) + '">' + formatEstado(t.estado) + '</span></td></tr>';
        html += '<tr><th>Prioridad:</th><td><span class="label label-' + prioridadLabel(t.prioridad) + '">' + t.prioridad + '</span></td></tr>';
        html += '<tr><th>Tipo:</th><td>' + formatEstado(t.tipo_servicio) + '</td></tr>';
        html += '</table></div>';

        html += '<div class="col-md-6">';
        html += '<table class="table table-condensed">';
        html += '<tr><th>Costo Est.:</th><td>$' + numberFormat(t.costo_estimado) + '</td></tr>';
        html += '<tr><th>Costo Final:</th><td>$' + numberFormat(t.costo_final) + '</td></tr>';
        html += '<tr><th>Creado:</th><td>' + t.fecha_creacion + '</td></tr>';
        html += '<tr><th>Cerrado:</th><td>' + (t.fecha_cierre || '-') + '</td></tr>';
        html += '<tr><th>Venta:</th><td>' + (t.idventa ? '#' + t.idventa : '-') + '</td></tr>';
        html += '</table></div></div>';

        html += '<div class="row"><div class="col-md-12">';
        html += '<h4>Descripcion</h4><div class="well well-sm" style="white-space:pre-wrap;">' + (t.descripcion || '') + '</div>';

        if (t.resumen_ia) {
            html += '<h4><i class="fa fa-magic"></i> Resumen IA</h4><div class="well well-sm bg-info">' + t.resumen_ia + '</div>';
        }
        if (t.notas) {
            html += '<h4>Notas</h4><div class="well well-sm" style="white-space:pre-wrap;">' + t.notas + '</div>';
        }
        html += '</div></div>';

        // Historial de mantenimientos
        html += '<div class="row"><div class="col-md-12"><h4><i class="fa fa-wrench"></i> Mantenimientos '
            + '<button class="btn btn-xs btn-warning" onclick="registrarMantenimiento(' + id + ')"><i class="fa fa-plus"></i> Agregar</button></h4>';
        html += '<div id="mantenimientos-lista"><i class="fa fa-spinner fa-spin"></i> Cargando...</div>';
        html += '</div></div>';

        // Mensajes WhatsApp
        html += '<div class="row"><div class="col-md-12"><h4><i class="fa fa-whatsapp"></i> Mensajes WhatsApp</h4>';
        html += '<div id="whatsapp-lista"><i class="fa fa-spinner fa-spin"></i> Cargando...</div>';
        html += '</div></div>';

        $("#ver-codigo").text(t.codigo_ticket);
        $("#detalle-ticket-body").html(html);

        // Cargar mantenimientos
        cargarMantenimientosTicket(id);
        cargarMensajesTicket(id);
    });
}

function cargarMantenimientosTicket(idticket) {
    $.getJSON("../landingV2/ajax/MantenimientoAjax.php?op=listPorTicket&idticket=" + idticket, function(data) {
        var items = data.aaData || [];
        if (items.length === 0) {
            $("#mantenimientos-lista").html('<p class="text-muted">Sin mantenimientos registrados</p>');
            return;
        }
        var html = '<table class="table table-condensed table-striped"><thead><tr><th>#</th><th>Fecha</th><th>Tipo</th><th>Descripcion</th><th>Tecnico</th><th>Duracion</th><th>Costo</th></tr></thead><tbody>';
        items.forEach(function(r) {
            html += '<tr><td>' + r[0] + '</td><td>' + r[1] + '</td><td>' + r[2] + '</td><td>' + r[3] + '</td><td>' + r[4] + '</td><td>' + r[5] + '</td><td>' + r[6] + '</td></tr>';
        });
        html += '</tbody></table>';
        $("#mantenimientos-lista").html(html);
    });
}

function cargarMensajesTicket(idticket) {
    $.getJSON("../landingV2/ajax/WhatsAppAjax.php?op=listPorTicket&idticket=" + idticket, function(data) {
        if (!data || data.length === 0) {
            $("#whatsapp-lista").html('<p class="text-muted">Sin mensajes</p>');
            return;
        }
        var html = '<div class="direct-chat-messages" style="max-height:200px;overflow:auto;">';
        data.forEach(function(msg) {
            var clase = msg.direccion === 'enviado' ? 'right' : '';
            html += '<div class="direct-chat-msg ' + clase + '">';
            html += '<div class="direct-chat-info clearfix"><span class="direct-chat-timestamp pull-' + (clase ? 'left' : 'right') + '">' + msg.fecha + '</span></div>';
            html += '<div class="direct-chat-text">' + msg.contenido + '</div>';
            html += '</div>';
        });
        html += '</div>';
        $("#whatsapp-lista").html(html);
    });
}

function editarTicket(id) {
    $.getJSON("../landingV2/ajax/TicketAjax.php?op=get&id=" + id, function(t) {
        $("#idticket").val(t.idticket);
        $("#idpersona").val(t.idpersona).trigger("change");
        $("#idusuario").val(t.idusuario).trigger("change");
        $("#titulo").val(t.titulo);
        $("#descripcion").val(t.descripcion);
        $("#tipo_servicio").val(t.tipo_servicio);
        $("#prioridad").val(t.prioridad);
        $("#costo_estimado").val(t.costo_estimado);
        $("#modalTicketTitulo").text("Editar Ticket: " + t.codigo_ticket);
        $("#modalTicket").modal("show");
    });
}

function cambiarEstadoTicket(nuevoEstado) {
    if (!ticketActualId) return;
    var obs = prompt("Observacion (opcional):", "");
    if (obs === null) return;

    $.ajax({
        url: "../landingV2/ajax/DashboardAjax.php?op=cambiarEstado",
        type: "POST",
        data: { idticket: ticketActualId, estado: nuevoEstado, observacion: obs },
        dataType: "json",
        success: function(resp) {
            if (resp.success) {
                alert("Estado actualizado a: " + formatEstado(nuevoEstado));
                $("#modalVerTicket").modal("hide");
                cargarTablaTickets(filtroEstado);
            } else {
                alert(resp.message || "Error al cambiar estado");
            }
        }
    });
}

function registrarMantenimiento(idticket) {
    $("#frmMantenimiento")[0].reset();
    $("#mnt-idticket").val(idticket);
    $("#mnt-repuestos").val("[]");
    $("#modalMantenimiento").modal("show");
}

function guardarMantenimiento() {
    $.ajax({
        url: "../landingV2/ajax/MantenimientoAjax.php?op=SaveOrUpdate",
        type: "POST",
        data: $("#frmMantenimiento").serialize(),
        success: function(resp) {
            alert(resp);
            $("#modalMantenimiento").modal("hide");
            if (ticketActualId) {
                cargarMantenimientosTicket(ticketActualId);
            }
        }
    });
}

function cargarClientes() {
    // Cargar clientes del sistema base (tabla persona tipo Cliente)
    // Ajustar ruta segun ubicacion del ajax de personas del sistema base
    try {
        $.getJSON("../ajax/persona.php?op=listarClientes", function(data) {
            var sel = $("#idpersona");
            (data || []).forEach(function(p) {
                sel.append('<option value="' + p.idpersona + '">' + p.nombre + '</option>');
            });
        });
    } catch(e) {}
}

function cargarTecnicos() {
    try {
        $.getJSON("../ajax/usuario.php?op=listar", function(data) {
            var sel = $("#idusuario");
            (data || []).forEach(function(u) {
                sel.append('<option value="' + u.idusuario + '">' + u.login + '</option>');
            });
        });
    } catch(e) {}
}

function estadoLabel(estado) {
    var map = { abierto: 'primary', en_diagnostico: 'info', en_proceso: 'warning', esperando_repuestos: 'default', finalizado: 'success', cancelado: 'danger' };
    return map[estado] || 'default';
}

function prioridadLabel(p) {
    var map = { baja: 'success', media: 'warning', alta: 'danger', critica: 'danger' };
    return map[p] || 'default';
}

function formatEstado(s) {
    return (s || '').replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
}

function numberFormat(num) {
    return parseInt(num || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
</script>

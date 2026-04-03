<?php
/**
 * Vista: Editor Visual de Flujos Conversacionales
 * UI tipo n8n/Zapier — nodos, flechas, estados
 */
?>
<section class="content-header">
    <h1>Editor de Flujos <small>SSolutions</small></h1>
</section>

<section class="content">
    <!-- Toolbar -->
    <div class="row" style="margin-bottom:10px;">
        <div class="col-md-12">
            <div class="btn-group">
                <button class="btn btn-primary" onclick="agregarNodo()"><i class="fa fa-plus"></i> Agregar Estado</button>
                <button class="btn btn-success" onclick="guardarFlujo()"><i class="fa fa-save"></i> Guardar</button>
                <button class="btn btn-warning" onclick="validarFlujo()"><i class="fa fa-check-circle"></i> Validar</button>
                <button class="btn btn-default" onclick="verJSON()"><i class="fa fa-code"></i> Ver JSON</button>
            </div>
            <div class="btn-group pull-right">
                <select id="sel-flujo" class="form-control" style="width:200px;display:inline-block;" onchange="cargarFlujoSeleccionado()">
                    <option value="">Cargando...</option>
                </select>
                <button class="btn btn-info btn-sm" onclick="cargarListaFlujos()"><i class="fa fa-refresh"></i></button>
            </div>
        </div>
    </div>

    <!-- Info de validación -->
    <div id="panel-validacion" style="display:none;" class="row">
        <div class="col-md-12">
            <div class="callout" id="callout-validacion"></div>
        </div>
    </div>

    <!-- Canvas del editor -->
    <div class="row">
        <div class="col-md-9">
            <div class="box box-primary" style="min-height:500px;">
                <div class="box-body" style="padding:0;position:relative;overflow:auto;">
                    <div id="flow-canvas" style="width:100%;min-height:500px;position:relative;background:#f8f9fa;background-image:radial-gradient(circle,#ddd 1px,transparent 1px);background-size:20px 20px;">
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de propiedades -->
        <div class="col-md-3">
            <div class="box box-default" id="panel-props" style="display:none;">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-pencil"></i> <span id="props-titulo">Estado</span></h3>
                    <button class="btn btn-xs btn-danger pull-right" onclick="eliminarNodoSeleccionado()"><i class="fa fa-trash"></i></button>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label>ID del estado</label>
                        <input type="text" id="prop-id" class="form-control input-sm" placeholder="ej: cotizacion">
                    </div>
                    <div class="form-group">
                        <label>Mensaje</label>
                        <textarea id="prop-mensaje" class="form-control" rows="3" placeholder="Hola {nombre}! ..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Opciones (botones, uno por línea)</label>
                        <textarea id="prop-opciones" class="form-control" rows="2" placeholder="Opción 1&#10;Opción 2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Transiciones</label>
                        <div id="props-transiciones"></div>
                        <button class="btn btn-xs btn-default" onclick="agregarTransicion()"><i class="fa fa-plus"></i> Transición</button>
                    </div>
                    <div class="form-group">
                        <label>Acción al entrar</label>
                        <select id="prop-accion" class="form-control input-sm">
                            <option value="">Ninguna</option>
                            <option value="confirmar_servicio">Confirmar servicio</option>
                            <option value="confirmar_cita">Confirmar cita</option>
                            <option value="crear_ticket">Crear ticket</option>
                        </select>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" id="prop-final"> Es estado final</label>
                    </div>
                    <button class="btn btn-primary btn-block btn-sm" onclick="aplicarPropiedades()"><i class="fa fa-check"></i> Aplicar</button>
                </div>
            </div>

            <!-- Historial de versiones -->
            <div class="box box-default">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-history"></i> Versiones</h3>
                </div>
                <div class="box-body" id="panel-versiones" style="max-height:250px;overflow-y:auto;">
                    <small class="text-muted">Cargando...</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal JSON -->
    <div class="modal fade" id="modal-json">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h4>JSON del Flujo</h4></div>
                <div class="modal-body">
                    <textarea id="json-raw" class="form-control" rows="20" style="font-family:monospace;font-size:12px;"></textarea>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="importarJSON()">Importar JSON</button>
                    <button class="btn btn-default" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.flow-node {
    position: absolute;
    min-width: 160px;
    max-width: 220px;
    background: #fff;
    border: 2px solid #3c8dbc;
    border-radius: 10px;
    padding: 10px 14px;
    cursor: move;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    z-index: 10;
    user-select: none;
    transition: box-shadow 0.2s;
}
.flow-node:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.15); }
.flow-node.selected { border-color: #f39c12; box-shadow: 0 0 0 3px rgba(243,156,18,0.3); }
.flow-node.node-final { border-color: #27ae60; background: #f0fff0; }
.flow-node.node-inicio { border-color: #9b59b6; background: #f8f0ff; }
.flow-node .node-title { font-weight: 700; font-size: 13px; margin-bottom: 4px; }
.flow-node .node-msg { font-size: 11px; color: #777; max-height: 40px; overflow: hidden; }
.flow-node .node-badge { position: absolute; top: -8px; right: -8px; font-size: 10px; }
.flow-arrow { position: absolute; z-index: 5; pointer-events: none; }

/* Transición inputs */
.trans-row { display: flex; gap: 4px; margin-bottom: 4px; }
.trans-row input, .trans-row select { font-size: 11px; }
</style>

<script>
var FLUJOS_URL = "" + AJAX + "FlujosAjax.php";
var nodos = {};
var selectedNode = null;
var dragState = null;

// ============ INIT ============
$(document).ready(function() {
    cargarListaFlujos();
    initCanvas();
});

function initCanvas() {
    var canvas = document.getElementById('flow-canvas');
    canvas.addEventListener('mousedown', function(e) {
        if (e.target === canvas) { deselectAll(); }
    });
}

// ============ NODOS ============
function agregarNodo(id, data) {
    id = id || 'estado_' + Date.now();
    data = data || { mensaje: '', opciones: [], transiciones: {}, accion: '', es_final: false };

    var x = data._x || 50 + Object.keys(nodos).length * 200;
    var y = data._y || 100;

    var cls = 'flow-node';
    if (id === 'inicio') cls += ' node-inicio';
    if (data.es_final) cls += ' node-final';

    var badge = '';
    if (id === 'inicio') badge = '<span class="node-badge label label-purple">INICIO</span>';
    if (data.es_final) badge = '<span class="node-badge label label-success">FIN</span>';
    if (data.accion) badge = '<span class="node-badge label label-warning"><i class="fa fa-bolt"></i></span>';

    var msg = (data.mensaje || data.mensaje_bienvenida || '').substring(0, 60);

    var html = '<div class="' + cls + '" id="node-' + id + '" style="left:' + x + 'px;top:' + y + 'px;" '
        + 'onmousedown="startDrag(event,\'' + id + '\')" onclick="selectNode(\'' + id + '\')">'
        + badge
        + '<div class="node-title">' + id + '</div>'
        + '<div class="node-msg">' + escapeHtml(msg) + '</div>'
        + '</div>';

    $('#flow-canvas').append(html);
    nodos[id] = { ...data, _x: x, _y: y };
    dibujarFlechas();
}

function eliminarNodoSeleccionado() {
    if (!selectedNode || selectedNode === 'inicio') {
        alert('No puedes eliminar el estado "inicio"');
        return;
    }
    if (!confirm('Eliminar estado "' + selectedNode + '"?')) return;
    $('#node-' + selectedNode).remove();
    delete nodos[selectedNode];
    selectedNode = null;
    $('#panel-props').hide();
    dibujarFlechas();
}

// ============ DRAG ============
function startDrag(e, id) {
    e.stopPropagation();
    var el = document.getElementById('node-' + id);
    var startX = e.clientX - el.offsetLeft;
    var startY = e.clientY - el.offsetTop;

    function onMove(ev) {
        el.style.left = (ev.clientX - startX) + 'px';
        el.style.top = (ev.clientY - startY) + 'px';
        nodos[id]._x = el.offsetLeft;
        nodos[id]._y = el.offsetTop;
        dibujarFlechas();
    }
    function onUp() {
        document.removeEventListener('mousemove', onMove);
        document.removeEventListener('mouseup', onUp);
    }
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', onUp);
}

// ============ SELECCION + PROPIEDADES ============
function selectNode(id) {
    deselectAll();
    selectedNode = id;
    $('#node-' + id).addClass('selected');
    $('#panel-props').show();
    $('#props-titulo').text(id);

    var data = nodos[id] || {};
    $('#prop-id').val(id);
    $('#prop-mensaje').val(data.mensaje || data.mensaje_bienvenida || '');
    $('#prop-opciones').val((data.opciones || []).join('\n'));
    $('#prop-accion').val(data.accion || '');
    $('#prop-final').prop('checked', !!data.es_final);

    // Transiciones
    var html = '';
    var trans = data.transiciones || {};
    Object.keys(trans).forEach(function(intencion) {
        html += transicionRow(intencion, trans[intencion]);
    });
    $('#props-transiciones').html(html);
}

function deselectAll() {
    $('.flow-node').removeClass('selected');
    selectedNode = null;
    $('#panel-props').hide();
}

function transicionRow(intencion, destino) {
    var intenciones = ['ACEPTAR_SERVICIO','RECHAZAR_SERVICIO','CONSULTAR_PRECIO','CONSULTAR_ESTADO','AGENDAR_CITA','SALUDO','default','servicio_seleccionado'];
    var opts = intenciones.map(function(i) {
        return '<option value="' + i + '"' + (i === intencion ? ' selected' : '') + '>' + i + '</option>';
    }).join('');

    var destOpts = '';
    Object.keys(nodos).forEach(function(n) {
        destOpts += '<option value="' + n + '"' + (n === destino ? ' selected' : '') + '>' + n + '</option>';
    });
    destOpts += '<option value="respuesta_ia"' + (destino === 'respuesta_ia' ? ' selected' : '') + '>respuesta_ia</option>';

    return '<div class="trans-row">'
        + '<select class="form-control input-sm trans-intent">' + opts + '</select>'
        + '<select class="form-control input-sm trans-dest">' + destOpts + '</select>'
        + '<button class="btn btn-xs btn-danger" onclick="$(this).parent().remove()"><i class="fa fa-times"></i></button>'
        + '</div>';
}

function agregarTransicion() {
    $('#props-transiciones').append(transicionRow('default', 'inicio'));
}

function aplicarPropiedades() {
    if (!selectedNode) return;
    var newId = $('#prop-id').val().trim().replace(/\s+/g, '_').toLowerCase();
    if (!newId) { alert('ID requerido'); return; }

    var transiciones = {};
    $('.trans-row').each(function() {
        var intent = $(this).find('.trans-intent').val();
        var dest = $(this).find('.trans-dest').val();
        if (intent && dest) transiciones[intent] = dest;
    });

    var data = {
        mensaje: $('#prop-mensaje').val(),
        opciones: $('#prop-opciones').val().split('\n').filter(Boolean),
        transiciones: transiciones,
        accion: $('#prop-accion').val(),
        es_final: $('#prop-final').is(':checked'),
        _x: nodos[selectedNode]._x,
        _y: nodos[selectedNode]._y
    };

    // Si cambió el ID, renombrar
    if (newId !== selectedNode) {
        delete nodos[selectedNode];
        $('#node-' + selectedNode).remove();
    }

    nodos[newId] = data;
    $('#node-' + selectedNode).remove();
    agregarNodo(newId, data);
    selectNode(newId);
}

// ============ FLECHAS (SVG) ============
function dibujarFlechas() {
    $('.flow-arrow').remove();
    var canvas = document.getElementById('flow-canvas');

    Object.keys(nodos).forEach(function(id) {
        var trans = nodos[id].transiciones || {};
        var srcEl = document.getElementById('node-' + id);
        if (!srcEl) return;

        Object.values(trans).forEach(function(destId) {
            if (destId === 'respuesta_ia') return;
            var destEl = document.getElementById('node-' + destId);
            if (!destEl) return;

            var x1 = srcEl.offsetLeft + srcEl.offsetWidth / 2;
            var y1 = srcEl.offsetTop + srcEl.offsetHeight;
            var x2 = destEl.offsetLeft + destEl.offsetWidth / 2;
            var y2 = destEl.offsetTop;

            var svg = '<svg class="flow-arrow" style="position:absolute;left:0;top:0;width:100%;height:100%;pointer-events:none;">'
                + '<defs><marker id="arr" markerWidth="8" markerHeight="6" refX="8" refY="3" orient="auto"><polygon points="0 0, 8 3, 0 6" fill="#999"/></marker></defs>'
                + '<line x1="' + x1 + '" y1="' + y1 + '" x2="' + x2 + '" y2="' + y2 + '" stroke="#999" stroke-width="2" marker-end="url(#arr)"/>'
                + '</svg>';
            $(canvas).append(svg);
        });
    });
}

// ============ JSON ============
function buildJSON() {
    var estados = {};
    Object.keys(nodos).forEach(function(id) {
        var n = nodos[id];
        var estado = {};
        if (n.mensaje) estado.mensaje = n.mensaje;
        if (n.mensaje_bienvenida) estado.mensaje_bienvenida = n.mensaje_bienvenida;
        if (n.opciones && n.opciones.length) estado.opciones = n.opciones;
        if (n.transiciones && Object.keys(n.transiciones).length) estado.transiciones = n.transiciones;
        if (n.accion) estado.accion = n.accion;
        if (n.es_final) estado.es_final = true;
        if (n.opciones_from) estado.opciones_from = n.opciones_from;
        if (n.tipo_input) estado.tipo_input = n.tipo_input;
        estados[id] = estado;
    });
    return { estados: estados };
}

function verJSON() {
    $('#json-raw').val(JSON.stringify(buildJSON(), null, 2));
    $('#modal-json').modal('show');
}

function importarJSON() {
    try {
        var data = JSON.parse($('#json-raw').val());
        cargarDesdeJSON(data);
        $('#modal-json').modal('hide');
    } catch(e) {
        alert('JSON inválido: ' + e.message);
    }
}

function cargarDesdeJSON(data) {
    // Limpiar canvas
    nodos = {};
    $('#flow-canvas').find('.flow-node, .flow-arrow').remove();

    var estados = data.estados || {};
    var keys = Object.keys(estados);
    keys.forEach(function(id, i) {
        var d = estados[id];
        d._x = 50 + (i % 3) * 240;
        d._y = 60 + Math.floor(i / 3) * 160;
        agregarNodo(id, d);
    });
}

// ============ GUARDAR / VALIDAR / CARGAR ============
function guardarFlujo() {
    var json = buildJSON();
    var notas = prompt('Notas del cambio (opcional):', '');
    var estabilidad = confirm('Marcar como ESTABLE?\n(Cancelar = experimental)') ? 'estable' : 'experimental';

    $.post(FLUJOS_URL + "?op=guardarFlujo", {
        nombre: 'principal',
        pasos: JSON.stringify(json),
        notas: notas || '',
        estabilidad: estabilidad
    }, function(resp) {
        var r = JSON.parse(resp);
        if (r.success) {
            mostrarValidacion('success', 'Flujo guardado (v' + r.version + '). '
                + (r.warnings.length ? 'Warnings: ' + r.warnings.join(', ') : 'Sin warnings.'));
            cargarListaFlujos();
        } else {
            mostrarValidacion('danger', 'Errores: ' + (r.errores || []).join(', '));
        }
    });
}

function validarFlujo() {
    $.post(FLUJOS_URL + "?op=validarFlujo", { pasos: JSON.stringify(buildJSON()) }, function(resp) {
        var r = JSON.parse(resp);
        if (r.valido) {
            mostrarValidacion('success', 'Flujo válido. ' + r.stats.total_estados + ' estados, '
                + r.stats.estados_finales + ' finales.'
                + (r.warnings.length ? '<br>Warnings: ' + r.warnings.join('<br>') : ''));
        } else {
            mostrarValidacion('danger', 'Errores:<br>' + r.errores.join('<br>')
                + (r.warnings.length ? '<br><br>Warnings:<br>' + r.warnings.join('<br>') : ''));
        }
    });
}

function mostrarValidacion(tipo, html) {
    $('#callout-validacion').attr('class', 'callout callout-' + tipo).html(html);
    $('#panel-validacion').show();
}

function cargarListaFlujos() {
    $.getJSON(FLUJOS_URL + "?op=listarFlujos", function(data) {
        var html = '<option value="">-- Nuevo flujo --</option>';
        var versionesHtml = '';
        (data || []).forEach(function(f) {
            var label = f.nombre + ' v' + f.version + (f.activo == 1 ? ' (activo)' : '');
            html += '<option value="' + f.idflujo + '">' + label + '</option>';

            var badge = f.activo == 1 ? 'success' : (f.estabilidad === 'experimental' ? 'warning' : 'default');
            versionesHtml += '<div style="padding:4px 0;border-bottom:1px solid #eee;">'
                + '<span class="label label-' + badge + '">' + (f.activo == 1 ? 'ACTIVO' : f.estabilidad) + '</span> '
                + '<strong>v' + f.version + '</strong> '
                + '<small class="text-muted">' + (f.notas_cambio || '') + '</small>'
                + (f.activo != 1 ? ' <a href="#" onclick="rollbackFlujo(' + f.idflujo + ')" class="text-warning"><i class="fa fa-undo"></i></a>' : '')
                + '</div>';
        });
        $('#sel-flujo').html(html);
        $('#panel-versiones').html(versionesHtml || '<small class="text-muted">Sin versiones</small>');
    });
}

function cargarFlujoSeleccionado() {
    var id = $('#sel-flujo').val();
    if (!id) {
        // Nuevo: crear flujo default
        nodos = {};
        $('#flow-canvas').find('.flow-node, .flow-arrow').remove();
        agregarNodo('inicio', { mensaje_bienvenida: 'Hola {nombre}! Como podemos ayudarte?', transiciones: { default: 'esperando_respuesta' }, _x: 300, _y: 40 });
        agregarNodo('esperando_respuesta', { transiciones: { ACEPTAR_SERVICIO: 'confirmado', default: 'respuesta_ia' }, _x: 300, _y: 200 });
        agregarNodo('confirmado', { mensaje: 'Servicio confirmado!', es_final: true, accion: 'confirmar_servicio', _x: 300, _y: 360 });
        return;
    }
    $.getJSON(FLUJOS_URL + "?op=getFlujo&id=" + id, function(f) {
        if (f && f.pasos) {
            cargarDesdeJSON(typeof f.pasos === 'string' ? JSON.parse(f.pasos) : f.pasos);
        }
    });
}

function rollbackFlujo(id) {
    if (!confirm('Rollback a esta versión?')) return;
    $.post(FLUJOS_URL + "?op=rollbackFlujo", { id: id }, function(resp) {
        var r = JSON.parse(resp);
        alert(r.message);
        cargarListaFlujos();
    });
}

function escapeHtml(t) {
    if (!t) return '';
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(t));
    return d.innerHTML;
}
</script>

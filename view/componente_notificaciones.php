<?php
/**
 * Componente: Notificaciones Navbar + Indicador IA + Mobile CSS
 *
 * Incluir en el template principal:
 *   <?php include 'view/componente_notificaciones.php'; ?>
 *
 * Agrega:
 * - Badge de notificaciones en navbar (polling cada 30s)
 * - Dropdown con notificaciones recientes
 * - Mobile-first CSS overrides
 * - Indicadores de source IA/fallback/rápida
 */
?>

<!-- ============================================================ -->
<!-- MOBILE-FIRST CSS -->
<!-- ============================================================ -->
<style>
/* Botones grandes en mobile */
@media (max-width: 768px) {
    .btn-lg { font-size: 18px; padding: 14px 20px; }
    .small-box .inner h3 { font-size: 28px; }
    .small-box .inner p { font-size: 13px; }
    .content-header h1 { font-size: 20px; }
    .box-header .box-title { font-size: 15px; }

    /* Acciones rápidas mobile */
    .mobile-quick-actions {
        position: fixed; bottom: 0; left: 0; right: 0;
        background: #fff; border-top: 2px solid #3c8dbc;
        display: flex; z-index: 1000; padding: 8px 4px;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    }
    .mobile-quick-actions .btn {
        flex: 1; margin: 0 3px; font-size: 11px; padding: 10px 5px;
        white-space: nowrap; border-radius: 8px;
    }
    .content { padding-bottom: 70px !important; }

    /* DataTables responsive */
    .dataTables_wrapper { overflow-x: auto; }
    table.dataTable { font-size: 12px; }

    /* Modals full-width en mobile */
    .modal-dialog { margin: 5px; width: auto; }
}

/* Indicadores de source IA */
.ia-badge { font-size: 10px; padding: 2px 6px; border-radius: 10px; margin-left: 4px; }
.ia-badge-ia { background: #9b59b6; color: #fff; }
.ia-badge-fallback { background: #e67e22; color: #fff; }
.ia-badge-rapida { background: #2ecc71; color: #fff; }

/* Notificaciones dropdown */
.notif-dropdown { max-height: 350px; overflow-y: auto; width: 320px; }
.notif-item { padding: 10px; border-bottom: 1px solid #f0f0f0; cursor: pointer; }
.notif-item:hover { background: #f5f5f5; }
.notif-item .notif-icon { width: 32px; height: 32px; border-radius: 50%; display: inline-flex;
    align-items: center; justify-content: center; margin-right: 8px; color: #fff; float: left; }
.notif-time { font-size: 11px; color: #999; }
</style>

<!-- ============================================================ -->
<!-- BARRA DE ACCIONES RAPIDAS MOBILE -->
<!-- ============================================================ -->
<div class="mobile-quick-actions hidden-lg hidden-md">
    <button class="btn btn-primary" onclick="cargarContenido('soporte_tickets')"><i class="fa fa-ticket"></i> Tickets</button>
    <button class="btn btn-success" onclick="cargarContenido('soporte_automatizacion')"><i class="fa fa-bolt"></i> Auto</button>
    <button class="btn btn-warning" onclick="cargarContenido('soporte_diagnosticos')"><i class="fa fa-stethoscope"></i> Diag</button>
    <button class="btn btn-default" onclick="cargarContenido('soporte_configuracion')"><i class="fa fa-cog"></i> Config</button>
</div>

<!-- ============================================================ -->
<!-- JAVASCRIPT: Notificaciones polling -->
<!-- ============================================================ -->
<script>
(function() {
    var NOTIF_URL = "" + AJAX + "NotificacionAjax.php";
    var _lastCount = 0;

    // Polling cada 30 segundos
    function checkNotificaciones() {
        $.getJSON(NOTIF_URL + "?op=noLeidas", function(data) {
            var total = data.total || 0;
            var items = data.items || [];

            // Actualizar badge
            var badge = $("#notif-badge");
            if (total > 0) {
                badge.text(total > 99 ? "99+" : total).show();
                // Flash si hay nuevas
                if (total > _lastCount) {
                    badge.addClass("animated flash");
                    setTimeout(function() { badge.removeClass("animated flash"); }, 1000);
                }
            } else {
                badge.hide();
            }
            _lastCount = total;

            // Actualizar dropdown
            var html = "";
            if (items.length === 0) {
                html = '<div class="text-center text-muted" style="padding:20px;">Sin notificaciones nuevas</div>';
            } else {
                items.forEach(function(n) {
                    var colorMap = {success:'#27ae60',danger:'#e74c3c',warning:'#f39c12',info:'#3498db','default':'#95a5a6'};
                    var bg = colorMap[n.color] || colorMap.info;
                    html += '<div class="notif-item" onclick="abrirNotificacion(' + n.idnotificacion + ', \'' + (n.url_accion||'') + '\')">'
                        + '<div class="notif-icon" style="background:' + bg + '"><i class="fa fa-' + n.icono + '"></i></div>'
                        + '<div style="overflow:hidden"><strong>' + n.titulo + '</strong><br>'
                        + '<small>' + n.mensaje.substring(0, 60) + '</small><br>'
                        + '<span class="notif-time">' + n.fecha + '</span></div></div>';
                });
                html += '<div class="text-center" style="padding:8px;">'
                    + '<a href="#" onclick="marcarTodasLeidas()">Marcar todas como leidas</a></div>';
            }
            $("#notif-dropdown-body").html(html);
        });
    }

    window.abrirNotificacion = function(id, url) {
        $.post(NOTIF_URL + "?op=marcarLeida", {id: id});
        if (url) cargarContenido(url);
        checkNotificaciones();
    };

    window.marcarTodasLeidas = function() {
        $.post(NOTIF_URL + "?op=marcarTodasLeidas", {}, function() {
            checkNotificaciones();
        });
    };

    // Verificar onboarding al cargar
    function checkOnboarding() {
        $.getJSON(NOTIF_URL + "?op=onboardingEstado", function(d) {
            if (d && !d.completado && d.progreso < 100) {
                // Mostrar banner de onboarding
                if (!sessionStorage.getItem('ob_dismissed')) {
                    $(".content-header").first().after(
                        '<div class="callout callout-info" id="ob-banner" style="margin:10px 15px;">'
                        + '<button type="button" class="close" onclick="$(\'#ob-banner\').hide();sessionStorage.setItem(\'ob_dismissed\',1);">&times;</button>'
                        + '<h4><i class="fa fa-rocket"></i> Configura tu negocio</h4>'
                        + '<p>Progreso: <strong>' + d.progreso + '%</strong> — '
                        + '<a href="#" onclick="cargarContenido(\'soporte_onboarding\')">Completar configuracion</a></p>'
                        + '</div>'
                    );
                }
            }
        });
    }

    // Inicializar
    $(document).ready(function() {
        checkNotificaciones();
        checkOnboarding();
        setInterval(checkNotificaciones, 30000);
    });
})();
</script>

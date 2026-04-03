<script>
// Auto-detectar base URL (funciona en local y producción)
var SS_BASE = (function() {
    var path = window.location.pathname;
    if (path.indexOf('/landingV2/') !== -1) return '../landingV2/';
    return '/';
})();
var AJAX = SS_BASE + 'ajax/';
var API = SS_BASE + 'api/';
</script>

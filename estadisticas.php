<?php
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/Database.php';
requireLogin();
$user = getUsuarioActual();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas - Wabot</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-brand"><h2>&#128172; Wabot</h2><span>WhatsApp Multiagente</span></div>
        <div class="sidebar-user">
            <div class="avatar"><?= strtoupper(substr($user['nombre'], 0, 1)) ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($user['nombre']) ?></div>
                <div class="user-rol"><?= $user['rol'] ?></div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <a href="index.php"><span class="nav-icon">&#128202;</span> Dashboard</a>
            <a href="conversaciones.php"><span class="nav-icon">&#128172;</span> Conversaciones</a>
            <a href="conocimiento.php"><span class="nav-icon">&#128196;</span> Conocimiento</a>
            <a href="estadisticas.php" class="active"><span class="nav-icon">&#128200;</span> Estadísticas</a>
            <?php if ($user['rol'] === 'admin'): ?>
                <div class="nav-section">Administración</div>
                <a href="plantillas.php"><span class="nav-icon">&#128233;</span> Plantillas</a>
                <a href="usuarios.php"><span class="nav-icon">&#128101;</span> Usuarios</a>
            <?php endif; ?>
        </nav>
        <div class="sidebar-footer"><a href="logout.php">&#128682; Cerrar sesión</a></div>
    </aside>
    <div class="main-content">
        <div class="main-header"><h3>&#128200; Estadísticas</h3></div>
        <div class="admin-content analytics-content">
            <div class="cards" id="metrics-cards"></div>
            <div class="section"><h3>Rendimiento por agente</h3><div class="table-wrapper" id="agent-table"></div></div>
            <div class="section"><h3>Actividad por hora</h3><div class="table-wrapper" id="hour-table"></div></div>
            <div class="section"><h3>Conversaciones por departamento</h3><div class="table-wrapper" id="dept-table"></div></div>
        </div>
    </div>
    <script>
        function loadMetrics() {
            fetch('ajax/metrics.php').then(r=>r.json()).then(data=>{
                document.getElementById('metrics-cards').innerHTML=`
                    <div class="card"><div class="card-value">${data.total}</div><div class="card-label">Total respuestas</div></div>
                    <div class="card"><div class="card-value">${data.promedio_seg}s</div><div class="card-label">Tiempo promedio</div></div>
                    <div class="card"><div class="card-value">${data.minimo_seg}s</div><div class="card-label">Respuesta más rápida</div></div>
                    <div class="card"><div class="card-value">${data.maximo_seg}s</div><div class="card-label">Respuesta más lenta</div></div>
                    <div class="card card-secondary"><div class="card-value">${data.humano_count}</div><div class="card-label">Por humanos</div></div>
                    <div class="card card-secondary"><div class="card-value">${data.ia_count}</div><div class="card-label">Por IA</div></div>`;
                let h='<table class="table"><thead><tr><th>Agente</th><th>Total</th><th>Promedio</th></tr></thead><tbody>';
                if(data.por_agente.length===0) h+='<tr><td colspan="3" style="text-align:center;color:#8696a0;">Sin datos</td></tr>';
                else data.por_agente.forEach(a=>{h+=`<tr><td>${esc(a.nombre)}</td><td>${a.total}</td><td>${Math.round(a.promedio)}s</td></tr>`;});
                document.getElementById('agent-table').innerHTML=h+'</tbody></table>';
                let h2='<table class="table"><thead><tr><th>Hora</th><th>Mensajes</th></tr></thead><tbody>';
                if(data.por_hora.length===0) h2+='<tr><td colspan="2" style="text-align:center;color:#8696a0;">Sin datos</td></tr>';
                else data.por_hora.forEach(h=>{h2+=`<tr><td>${h.hora}:00</td><td>${h.total}</td></tr>`;});
                document.getElementById('hour-table').innerHTML=h2+'</tbody></table>';
                let h3='<table class="table"><thead><tr><th>Departamento</th><th>Conversaciones</th></tr></thead><tbody>';
                if(data.por_departamento.length===0) h3+='<tr><td colspan="2" style="text-align:center;color:#8696a0;">Sin datos</td></tr>';
                else data.por_departamento.forEach(d=>{h3+=`<tr><td>${esc(d.departamento)}</td><td>${d.total}</td></tr>`;});
                document.getElementById('dept-table').innerHTML=h3+'</tbody></table>';
            });
        }
        function esc(s){const d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}
        loadMetrics(); setInterval(loadMetrics,15000);
    </script>
</body>
</html>

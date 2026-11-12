<?php
session_start();
$db = new PDO('sqlite:apple.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE IF NOT EXISTS grades (id INTEGER PRIMARY KEY AUTOINCREMENT, subject TEXT, grade REAL, description TEXT, weight INTEGER DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");
$db->exec("CREATE TABLE IF NOT EXISTS subjects (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE NOT NULL)");

try { $db->exec("ALTER TABLE grades ADD COLUMN weight INTEGER DEFAULT 1"); } catch(Exception $e) {}

if ($db->query("SELECT COUNT(*) FROM subjects")->fetchColumn() == 0) {
    $defaults = ["Rekenen", "Taal", "Spelling", "Technisch lezen", "Begrijpend lezen", "Schrijven", "Engels", "Natuur en techniek", "Tekenen", "Handvaardigheid", "Gymnastiek", "Mens & Maatschappij", "Papiaments", "Christelijke Educatie", "Tekstanalyse", "Spaans"];
    foreach ($defaults as $d) { $db->prepare("INSERT INTO subjects (name) VALUES (?)")->execute([$d]); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'add') { 
        $w = isset($_POST['weight']) ? intval($_POST['weight']) : 1;
        $db->prepare("INSERT INTO grades (subject, grade, description, weight) VALUES (?, ?, ?, ?)")->execute([$_POST['subject'], floatval($_POST['grade']), trim($_POST['desc'] ?? ''), $w]); 
    }
    if ($_POST['action'] === 'del') { 
        $id = intval($_POST['id']);
        $stmt = $db->prepare("SELECT * FROM grades WHERE id = ?"); $stmt->execute([$id]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) { $_SESSION['undo_grade'] = $row; $db->prepare("DELETE FROM grades WHERE id = ?")->execute([$id]); }
    }
    if ($_POST['action'] === 'undo' && isset($_SESSION['undo_grade'])) {
        $g = $_SESSION['undo_grade'];
        $db->prepare("INSERT INTO grades (subject, grade, description, weight) VALUES (?, ?, ?, ?)")->execute([$g['subject'], $g['grade'], $g['description'], $g['weight']]);
        unset($_SESSION['undo_grade']);
    }
    if ($_POST['action'] === 'add_sub') { try { $db->prepare("INSERT INTO subjects (name) VALUES (?)")->execute([trim($_POST['sub_name'])]); } catch(Exception $e) {} }
    if ($_POST['action'] === 'del_sub') { $db->prepare("DELETE FROM subjects WHERE name = ?")->execute([$_POST['sub_name']]); $db->prepare("DELETE FROM grades WHERE subject = ?")->execute([$_POST['sub_name']]); }
    header("Location: " . $_SERVER['PHP_SELF']); exit;
}

$subjects = $db->query("SELECT name FROM subjects")->fetchAll(PDO::FETCH_COLUMN);
$allGrades = $db->query("SELECT * FROM grades ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$data = []; foreach ($subjects as $sub) { $data[$sub] = []; }
foreach ($allGrades as $g) { if (isset($data[$g['subject']])) { $data[$g['subject']][] = $g; } }

$overallSum = 0; $subjectCount = 0; $fails = 0;
foreach ($data as $sub => $grades) {
    if (count($grades) > 0) {
        $sum = 0; $totalWeight = 0;
        foreach ($grades as $g) { $sum += ($g['grade'] * $g['weight']); $totalWeight += $g['weight']; }
        $overallSum += ($sum / $totalWeight); $subjectCount++;
    }
    foreach ($grades as $g) { if ($g['grade'] < 5.5) $fails++; }
}
$overallAvg = $subjectCount > 0 ? round($overallSum / $subjectCount, 1) : 0;

$prediction = "AGO"; $predColor = "glow";
if ($overallAvg >= 8) { $prediction = "HAVO"; $predColor = "g10"; }
elseif ($overallAvg >= 7) { $prediction = "VSBO"; $predColor = "g89"; }
elseif ($overallAvg >= 6) { $prediction = "AGO"; $predColor = "g67"; }
?>
<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Cijferlijst Pro</title><link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="deepseek.png"><meta name="theme-color" content="#000000"><script src="https://cdn.tailwindcss.com"></script><script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { background: #000; color: white; font-family: 'SF Pro Display', 'SamsungOne', -apple-system, BlinkMacSystemFont, sans-serif; overflow: hidden; -webkit-font-smoothing: antialiased; font-size: 14px; }
.bg-aurora { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; z-index: -1; overflow: hidden; background: #050505; }
.blob { position: absolute; filter: blur(80px); opacity: 0.4; border-radius: 50%; animation: float 20s infinite alternate; }
.blob-1 { top: -10%; left: -10%; width: 50vw; height: 50vw; background: #3b82f6; animation-delay: 0s; }
.blob-2 { bottom: -10%; right: -10%; width: 60vw; height: 60vw; background: #ec4899; animation-delay: -5s; }
.blob-3 { top: 40%; left: 30%; width: 40vw; height: 40vh; background: #10b981; animation-delay: -10s; }
@keyframes float { 0% { transform: translate(0, 0) scale(1); } 100% { transform: translate(10vw, 10vh) scale(1.2); } }
.glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(40px) saturate(200%); -webkit-backdrop-filter: blur(40px) saturate(200%); border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 1px 1px rgba(255,255,255,0.3), inset 0 -1px 1px rgba(0,0,0,0.5), 0 10px 20px rgba(0,0,0,0.4); transition: all 0.3s ease; }
.glass:hover { background: rgba(255,255,255,0.06); border-color: rgba(255,255,255,0.2); }
@keyframes pulseRed { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 10px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
.glow { background: #ef4444; animation: pulseRed 2s infinite; color: white; }
@keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-3px); } 75% { transform: translateX(3px); } }
.shake { animation: shake 0.3s ease-in-out; }
@keyframes bounceIn { 0% { transform: scale(0.8); opacity: 0; } 80% { transform: scale(1.02); } 100% { transform: scale(1); opacity: 1; } }
.bounce-in { animation: bounceIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes slideUp { from { transform: translateY(50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.slide-up { animation: slideUp 0.4s ease-out; }
@keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.slide-down { animation: slideDown 0.3s ease-out; }
@keyframes exclamation { 0%, 100% { transform: translateY(0) scale(1); opacity: 1; } 50% { transform: translateY(-5px) scale(1.1); opacity: 0.8; } }
.huge-exclamation { animation: exclamation 0.8s infinite; }
.g10 { background: #10b981; box-shadow: 0 0 10px #10b981; color: white; } 
.g89 { background: #3b82f6; box-shadow: 0 0 10px #3b82f6; color: white; } 
.g67 { background: #eab308; box-shadow: 0 0 10px #eab308; color: black; } 
#sidebar { transition: transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1); transform: translateX(-100%); }
#sidebar.open { transform: translateX(0); }
#main { transition: all 0.5s cubic-bezier(0.2, 0.8, 0.2, 1); }
#main.shift { transform: translateX(70%) scale(0.92); filter: blur(10px); border-radius: 2rem; overflow: hidden; }
.hide { display: none !important; } ::-webkit-scrollbar { width: 0px; }
.progress-bar { height: 6px; border-radius: 3px; background: rgba(255,255,255,0.1); overflow: hidden; }
.progress-fill { height: 100%; border-radius: 3px; transition: width 1s cubic-bezier(0.4, 0, 0.2, 1); }
.deepseek-brand { position: fixed; bottom: 16px; right: 16px; z-index: 50; display: flex; align-items: center; gap: 6px; background: rgba(0,0,0,0.7); backdrop-filter: blur(10px); padding: 6px 12px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); pointer-events: none; box-shadow: 0 4px 12px rgba(0,0,0,0.5); }
.deepseek-brand span { font-size: 10px; font-weight: 600; color: #e2e8f0; letter-spacing: 0.5px; }
</style>
</head>
<body>
<div class="bg-aurora"><div class="blob blob-1"></div><div class="blob blob-2"></div><div class="blob blob-3"></div></div>
<div class="deepseek-brand">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 12C2 12 4 6 10 6C14 6 16 8 18 10C20 12 22 14 22 14C22 14 20 16 16 16C12 16 10 14 8 12C6 10 4 12 2 12Z" fill="#4D6BFE"/><path d="M18 10C18 10 20 8 22 8C22 8 22 10 20 12" fill="#4D6BFE"/><circle cx="15" cy="11" r="1" fill="white"/></svg>
    <span>Gemaakt door DeepSeek</span>
</div>
<?php if(isset($_SESSION['undo_grade'])): ?>
<div class="fixed top-20 left-1/2 transform -translate-x-1/2 z-50 glass px-4 py-2 rounded-xl flex items-center gap-3 text-sm slide-down">
    <span>Cijfer verwijderd.</span>
    <form method="POST" class="m-0"><input type="hidden" name="action" value="undo"><button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded-lg font-bold">Undo</button></form>
</div>
<?php endif; ?>

<button onclick="toggle()" id="m-btn" class="fixed top-6 left-6 z-50 w-12 h-12 glass rounded-xl flex items-center justify-center font-bold text-xl text-white shadow-xl active:scale-90 transition-transform">M</button>

<aside id="sidebar" class="fixed inset-y-0 left-0 w-[80%] max-w-[320px] z-40 glass p-5 pt-20 flex flex-col">
    <div class="flex justify-between items-center mb-4 px-1">
        <h2 class="text-xs text-gray-400 font-bold uppercase tracking-widest">Menu</h2>
        <span id="live-clock" class="text-xs text-gray-500 font-mono"></span>
    </div>
    <button onclick="window.scrollTo(0,0); toggle();" class="w-full text-left px-4 py-3.5 mb-2 rounded-xl bg-white/10 hover:bg-white/20 active:bg-white/30 transition-colors flex items-center gap-3 font-bold text-sm text-white border border-white/10 shadow-lg">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
        Dashboard
    </button>
    <h2 class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-4 mb-2 px-1">Vakken</h2>
    <input type="text" id="search-bar" onkeyup="filterSubjects()" placeholder="Zoek vak..." class="w-full bg-black/30 border border-white/10 p-2.5 rounded-lg text-sm mb-3 focus:outline-none focus:border-white/40 text-white">
    <div id="subject-list" class="overflow-y-auto space-y-2.5 flex-1 pb-4">
        <?php foreach ($data as $sub => $grades): $avg = count($grades) > 0 ? round(array_sum(array_column($grades, 'grade')) / count($grades), 1) : null; ?>
        <div onclick="openDetail('<?= htmlspecialchars($sub) ?>')" class="subject-item flex justify-between items-center bg-white/5 hover:bg-white/10 p-4 rounded-xl border border-white/5 cursor-pointer active:scale-95 transition-all overflow-hidden relative" data-name="<?= strtolower(htmlspecialchars($sub)) ?>">
            <span class="font-bold text-sm text-gray-100"><?= htmlspecialchars($sub) ?></span>
            <?php if($avg !== null): ?><span class="text-xs font-black px-2.5 py-1 rounded-md bg-white/10 text-white shadow-inner"><?= number_format($avg, 1, ',', '') ?></span><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <form method="POST" class="flex gap-2 pt-3 border-t border-white/10 mt-2">
        <input type="hidden" name="action" value="add_sub"><input type="text" name="sub_name" placeholder="Nieuw vak..." required class="flex-1 bg-black/30 border border-white/10 p-2.5 rounded-lg text-sm focus:outline-none focus:border-white/40 text-white">
        <button type="submit" class="bg-white text-black px-4 rounded-lg font-bold text-sm active:scale-95 transition-transform">+</button>
    </form>
</aside>

<main id="main" class="absolute inset-0 p-4 pt-20 overflow-y-auto w-full h-full">
    <div class="max-w-md mx-auto pb-24">
        <h1 class="text-3xl font-bold tracking-tight mb-4 drop-shadow-lg">Cijferlijst</h1>
        <div class="glass rounded-2xl p-5 mb-3 slide-up relative overflow-hidden" style="animation-delay: 0.1s;">
            <p class="text-xs text-gray-400 font-medium mb-1">Voorspelling</p>
            <div class="flex items-end gap-2"><span class="text-3xl font-black <?= $predColor ?> px-3 py-1 rounded-lg shadow-lg"><?= $prediction ?></span></div>
        </div>
        <div class="glass rounded-2xl p-5 mb-3 flex justify-between items-center slide-up" style="animation-delay: 0.2s;">
            <p class="text-xs text-gray-400 font-medium">Gemiddelde over alle vakken</p>
            <span class="text-2xl font-black text-white drop-shadow-md"><?= number_format($overallAvg, 1, ',', '') ?></span>
        </div>
        <div class="glass rounded-2xl p-5 mb-6 flex justify-between items-center slide-up" style="animation-delay: 0.3s;">
            <p class="text-xs text-gray-400 font-medium">Aantal onvoldoendes</p>
            <span class="text-2xl font-black <?= $fails > 0 ? 'text-red-400 drop-shadow-[0_0_8px_rgba(248,113,113,0.8)]' : 'text-green-400 drop-shadow-[0_0_8px_rgba(74,222,128,0.8)]' ?>"><?= $fails ?></span>
        </div>
        <h2 class="text-sm font-bold mb-3 text-gray-200">Jouw Vakken</h2>
        <div class="space-y-3">
            <?php $delay = 0.4; foreach ($data as $sub => $grades): 
                $avg = count($grades) > 0 ? round(array_sum(array_column($grades, 'grade')) / count($grades), 1) : null;
                $avgClass = $avg === null ? 'bg-gray-800 text-gray-500' : ($avg == 10 ? 'g10' : ($avg >= 8 ? 'g89' : ($avg >= 6 ? 'g67' : 'glow')));
                $barColor = $avg === null ? 'bg-gray-600' : ($avg == 10 ? 'bg-emerald-500' : ($avg >= 8 ? 'bg-blue-500' : ($avg >= 6 ? 'bg-yellow-500' : 'bg-red-500')));
                $barWidth = $avg === null ? 0 : ($avg * 10);
            ?>
            <div onclick="openDetail('<?= htmlspecialchars($sub) ?>')" class="glass rounded-2xl p-4 cursor-pointer active:scale-95 transition-all slide-up relative overflow-hidden" style="animation-delay: <?= $delay ?>s;">
                <div class="flex justify-between items-center mb-3">
                    <div><h3 class="text-sm font-bold text-white"><?= htmlspecialchars($sub) ?></h3><p class="text-[10px] text-gray-400 font-medium"><?= count($grades) ?> cijfers</p></div>
                    <div class="flex items-center gap-2"><?php if($avg !== null): ?><span class="text-sm font-black px-2 py-1 rounded-md <?= $avgClass ?>"><?= number_format($avg, 1, ',', '') ?></span><?php else: ?><span class="text-sm font-black px-2 py-1 rounded-md bg-gray-800 text-gray-500">-</span><?php endif; ?></div>
                </div>
                <div class="progress-bar"><div class="progress-fill <?= $barColor ?>" style="width: <?= $barWidth ?>%"></div></div>
            </div>
            <?php $delay += 0.05; endforeach; ?>
        </div>

        <!-- NIEUW VAK FORMULIER DASHBOARD -->
        <div class="mt-8 mb-4">
            <h2 class="text-sm font-bold mb-3 text-gray-200">Vak toevoegen</h2>
            <form method="POST" class="glass rounded-2xl p-2 flex gap-2 relative overflow-hidden slide-up" style="animation-delay: <?= $delay + 0.1 ?>s;">
                <input type="hidden" name="action" value="add_sub">
                <input type="text" name="sub_name" placeholder="Typ een nieuw vak..." required class="flex-1 bg-black/30 border border-white/10 p-3 rounded-xl text-sm focus:outline-none focus:border-white/40 text-white placeholder-gray-400">
                <button type="submit" class="bg-white text-black px-4 rounded-xl font-bold text-sm active:scale-95 transition-transform flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                </button>
            </form>
        </div>
    </div>
</main>

<div id="detail-modal" class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 hide flex items-end" onclick="closeDetail(event)">
    <div class="w-full max-w-md mx-auto glass rounded-t-3xl p-5 pb-8 h-[85vh] flex flex-col slide-up" onclick="event.stopPropagation()">
        <div class="flex justify-between items-center mb-3 pb-3 border-b border-white/10">
            <h2 id="d-title" class="text-lg font-bold">Vak</h2>
            <div class="flex items-center gap-1">
                <button onclick="closeDetail()" class="text-gray-400 p-1.5 hover:text-white transition-colors rounded-lg active:scale-90"><svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
                <form method="POST" onsubmit="return confirm('Vak verwijderen?');"><input type="hidden" name="action" value="del_sub"><input type="hidden" id="d-del-sub" name="sub_name" value=""><button class="text-red-400 p-1.5 bg-red-400/10 rounded-lg active:scale-90 transition-transform"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button></form>
            </div>
        </div>
        <div id="chart-container" class="w-full mb-3" style="height: 120px; position: relative;">
            <canvas id="gradeChart"></canvas>
        </div>
        <div id="d-list" class="flex-1 overflow-y-auto space-y-2 pr-1 mb-3"></div>
        <form method="POST" class="flex flex-col gap-1.5 bg-black/20 p-1.5 rounded-xl">
            <div class="flex gap-1.5">
                <input type="hidden" name="action" value="add"><input type="hidden" id="d-sub-input" name="subject" value="">
                <input type="number" name="grade" step="0.1" min="1" max="10" required placeholder="Cijfer" class="w-16 bg-black/30 border border-white/10 p-2 rounded-lg text-center font-bold text-sm focus:outline-none focus:border-white/40 text-white">
                <input type="text" name="desc" placeholder="Omschrijving" class="flex-1 bg-black/30 border border-white/10 p-2 rounded-lg text-xs focus:outline-none focus:border-white/40 text-white">
                <select name="weight" class="bg-black/30 border border-white/10 p-2 rounded-lg text-xs text-white focus:outline-none">
                    <option value="1">1x</option>
                    <option value="2">2x</option>
                    <option value="3">3x</option>
                </select>
                <button type="submit" class="bg-white text-black px-3 rounded-lg font-bold text-sm active:scale-95 transition-transform">+</button>
            </div>
        </form>
    </div>
</div>
<script>
    const appData = <?= json_encode($data) ?>;
    let clickCount = 0; let myChart = null;
    setInterval(() => { const now = new Date(); document.getElementById('live-clock').innerText = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0'); }, 1000);
    function filterSubjects() { const query = document.getElementById('search-bar').value.toLowerCase(); document.querySelectorAll('.subject-item').forEach(item => { item.style.display = item.dataset.name.includes(query) ? 'flex' : 'none'; }); }
    function vibrate() { if (navigator.vibrate) navigator.vibrate(15); }
    document.getElementById('m-btn').addEventListener('click', () => { vibrate(); clickCount++; if(clickCount >= 5) { document.querySelector('.bg-aurora').style.animation = 'spin 2s linear infinite'; setTimeout(() => document.querySelector('.bg-aurora').style.animation = '', 2000); clickCount = 0; } });
    function toggle() { document.getElementById('sidebar').classList.toggle('open'); document.getElementById('main').classList.toggle('shift'); }
    
    function openDetail(subject) {
        vibrate(); 
        document.getElementById('d-title').innerText = subject; 
        document.getElementById('d-sub-input').value = subject; 
        document.getElementById('d-del-sub').value = subject;
        
        const list = document.getElementById('d-list'); 
        list.innerHTML = ''; 
        const grades = appData[subject] || [];
        
        if (grades.length === 0) { 
            list.innerHTML = '<p class="text-gray-400 text-center mt-6 text-xs italic">Nog geen cijfers ingevuld.</p>'; 
        } else {
            grades.forEach((g, index) => {
                const n = parseFloat(g.grade); const style = n == 10 ? 'g10' : (n >= 8 ? 'g89' : (n >= 6 ? 'g67' : 'glow'));
                let fix = n <= 6 ? `<span class="text-[9px] uppercase text-red-400 font-bold bg-red-500/10 px-1.5 py-0.5 rounded mt-1 inline-block">Fix ${7-n}x op blaadje</span>` : '';
                let excl = n == 1 ? `<span class="huge-exclamation absolute -top-6 -left-2 text-3xl text-red-500 font-black z-50 drop-shadow-[0_0_8px_red]">!</span>` : '';
                let shakeClass = n == 1 ? 'shake' : '';
                let weightTxt = g.weight > 1 ? `<span class="text-[9px] text-gray-400 ml-1">(${g.weight}x)</span>` : '';
                list.innerHTML += `<div class="flex justify-between items-center bg-white/5 p-2.5 rounded-xl border border-white/5 relative bounce-in" style="animation-delay: ${index * 0.05}s"><div class="flex flex-col w-2/3"><span class="text-xs font-semibold text-gray-200">${g.description || 'Cijfer'} ${weightTxt}</span>${fix}</div><div class="flex items-center gap-2"><div class="relative ${excl?'mt-3':''}">${excl}<span class="w-9 h-9 rounded-lg flex items-center justify-center font-black text-base ${style} ${shakeClass}">${n.toString().replace('.', ',')}</span></div><form method="POST" class="m-0"><input type="hidden" name="action" value="del"><input type="hidden" name="id" value="${g.id}"><button onclick="vibrate()" class="text-gray-400 p-1 hover:text-red-400 transition-colors"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button></form></div></div>`;
            });
        }
        
        document.getElementById('detail-modal').classList.remove('hide');

        setTimeout(() => {
            const ctx = document.getElementById('gradeChart').getContext('2d');
            if(myChart) myChart.destroy();
            let labels = grades.map((_, i) => `#${grades.length - i}`).reverse();
            let dataPoints = grades.map(g => parseFloat(g.grade)).reverse();
            let gradient = ctx.createLinearGradient(0, 0, 0, 120);
            gradient.addColorStop(0, 'rgba(77, 107, 254, 0.6)');
            gradient.addColorStop(1, 'rgba(77, 107, 254, 0.0)');

            myChart = new Chart(ctx, {
                type: 'line',
                data: { labels: labels, datasets: [{ label: 'Cijfer', data: dataPoints, borderColor: '#4D6BFE', backgroundColor: gradient, borderWidth: 3, tension: 0.4, fill: true, pointBackgroundColor: '#fff', pointRadius: 3, pointHoverRadius: 5 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { min: 1, max: 10, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#888', font: {size: 10} } }, x: { grid: { display: false }, ticks: { color: '#888', font: {size: 10} } } } }
            });
        }, 50);
    }
    
    function closeDetail(e) { if(e && e.target.id !== 'detail-modal' && e.type === 'click' && e.target.closest('button') === null) return; document.getElementById('detail-modal').classList.add('hide'); }
    
    if ("Notification" in window && Notification.permission !== "granted" && Notification.permission !== "denied") { Notification.requestPermission(); }
    function checkNotif() { let fixes = 0; Object.values(appData).forEach(subs => subs.forEach(g => { if(g.grade<=6) fixes++; })); if(fixes > 0 && Notification.permission === "granted" && localStorage.getItem('notifDate') !== new Date().toDateString()) { new Notification("Cijferlijst ⚠️", { body: `Je moet ${fixes} cijfer(s) fixeren op een blaadje!` }); localStorage.setItem('notifDate', new Date().toDateString()); } }
    window.onload = checkNotif;
</script>
<script>
if ("serviceWorker" in navigator) {
  navigator.serviceWorker.register("sw.js");
}
</script>
</body>
</html>

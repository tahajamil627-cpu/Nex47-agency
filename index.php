<?php
/**
 * NEX - 47 CATALYS'S - Master All-in-One Engine & Router
 * Production Ready for Localhost (XAMPP) & Cloud (Railway, Render, Vercel)
 */
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Global Database Connection Function
function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    
    try {
        $host = getenv('MYSQLHOST') ?: (getenv('DB_HOST') ?: 'localhost');
        $user = getenv('MYSQLUSER') ?: (getenv('DB_USER') ?: 'root');
        $pass = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: ''));
        $name = getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: 'nex47_db');
        $port = getenv('MYSQLPORT') ?: (getenv('DB_PORT') ?: '3306');

        $url = getenv('MYSQL_URL') ?: getenv('DATABASE_URL');
        if ($url) {
            $p = parse_url($url);
            if ($p) {
                $host = $p['host'] ?? $host;
                $user = $p['user'] ?? $user;
                $pass = $p['pass'] ?? $pass;
                $port = $p['port'] ?? $port;
                $name = isset($p['path']) ? ltrim($p['path'], '/') : $name;
            }
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 3
        ];
        
        $pdo = new PDO($dsn, $user, $pass, $opts);
        
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `leads` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `client_name` VARCHAR(255) NOT NULL,
                `client_phone` VARCHAR(50) NOT NULL,
                `brand_name` VARCHAR(255) DEFAULT NULL,
                `service_type` VARCHAR(150) NOT NULL,
                `budget` VARCHAR(100) DEFAULT '$100 - $300',
                `message` TEXT DEFAULT NULL,
                `lead_source` VARCHAR(50) DEFAULT 'Website Form',
                `status` VARCHAR(50) DEFAULT 'new',
                `notes` TEXT DEFAULT NULL,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $pdo->exec("CREATE TABLE IF NOT EXISTS `admins` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(150) UNIQUE NOT NULL,
                `password` VARCHAR(255) NOT NULL,
                `role` VARCHAR(50) DEFAULT 'superadmin',
                `last_login` DATETIME DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $count = $pdo->query("SELECT COUNT(*) FROM `admins` WHERE `email` = 'admin@nex47.com'")->fetchColumn();
            if ($count == 0) {
                $hash = password_hash('samsparrow', PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO `admins` (`name`, `email`, `password`, `role`) VALUES (?,?,?,?)");
                $stmt->execute(['NEX-47 SuperAdmin', 'admin@nex47.com', $hash, 'superadmin']);
            }
        } catch (Exception $eTable) {}

        return $pdo;
    } catch (Exception $e) {
        return null;
    }
}

// Parse Route
$rawUri = $_SERVER['REQUEST_URI'] ?? '/';
$uri = parse_url($rawUri, PHP_URL_PATH);
$uri = rtrim($uri, '/');
if (empty($uri)) $uri = '/';

// 1. ADMIN LOGOUT
if ($uri === '/admin/logout' || $uri === '/admin/logout.php' || (isset($_GET['admin']) && $_GET['admin'] === 'logout')) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: /admin/login.php?logout=1');
    exit;
}

// 2. API SUBMIT LEAD
if ($uri === '/api/submit_lead.php' || (isset($_GET['api']) && $_GET['api'] === 'submit_lead')) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $name = trim($input['client_name'] ?? '');
    $phone = trim($input['client_phone'] ?? '');
    $brand = trim($input['brand_name'] ?? '');
    $service = trim($input['service_type'] ?? 'Social Media Marketing');
    $budget = trim($input['budget'] ?? '$100 - $300');
    $message = trim($input['message'] ?? '');
    $source = trim($input['lead_source'] ?? 'Website Form');

    if (empty($name) || empty($phone)) {
        echo json_encode(['success' => false, 'message' => 'Please provide name and phone.']);
        exit;
    }

    try {
        $pdo = getDBConnection();
        if ($pdo) {
            $stmt = $pdo->prepare("INSERT INTO `leads` (`client_name`, `client_phone`, `brand_name`, `service_type`, `budget`, `message`, `lead_source`, `status`) VALUES (?,?,?,?,?,?,?,'new')");
            $stmt->execute([$name, $phone, $brand, $service, $budget, $message, $source]);
        }
    } catch (Exception $e) {}
    echo json_encode(['success' => true, 'message' => 'Lead received!']);
    exit;
}

// 3. API GET LEADS
if ($uri === '/api/get_leads.php' || (isset($_GET['api']) && $_GET['api'] === 'get_leads')) {
    header('Content-Type: application/json');
    $status = $_GET['status'] ?? 'all';
    $search = trim($_GET['search'] ?? '');
    $leads = [];

    try {
        $pdo = getDBConnection();
        if ($pdo) {
            $sql = "SELECT * FROM `leads` WHERE 1=1";
            $params = [];
            if ($status !== 'all' && !empty($status)) {
                $sql .= " AND `status` = :status";
                $params[':status'] = $status;
            }
            if (!empty($search)) {
                $sql .= " AND (`client_name` LIKE :q OR `client_phone` LIKE :q OR `brand_name` LIKE :q)";
                $params[':q'] = "%$search%";
            }
            $sql .= " ORDER BY `created_at` DESC LIMIT 100";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $leads = $stmt->fetchAll();
        }
    } catch (Exception $e) {}

    echo json_encode(['success' => true, 'leads' => $leads]);
    exit;
}

// 4. API EXPORT LEADS CSV
if ($uri === '/api/export_leads.php' || (isset($_GET['api']) && $_GET['api'] === 'export_leads')) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=nex47_leads_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Client Name', 'Phone', 'Brand', 'Service', 'Budget', 'Status', 'Date']);
    try {
        $pdo = getDBConnection();
        if ($pdo) {
            $rows = $pdo->query("SELECT id, client_name, client_phone, brand_name, service_type, budget, status, created_at FROM leads ORDER BY id DESC")->fetchAll();
            foreach ($rows as $r) fputcsv($output, $r);
        }
    } catch (Exception $e) {}
    fclose($output);
    exit;
}

// 5. CHECK FOR ADMIN ROUTES
$isAdminRoute = ($uri === '/admin' || $uri === '/admin/login' || $uri === '/admin/login.php' || $uri === '/admin/index.php' || strpos($uri, '/admin') === 0 || isset($_GET['admin']));

if ($isAdminRoute) {
    // Self-contained router active

    // Built-in Self-Contained Controller
    $loginError = '';
    $logoutMsg = isset($_GET['logout']) ? 'You have been successfully logged out.' : '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['admin_pass_key'] ?? $_POST['password'] ?? '');
        $valid = false;

        try {
            $pdo = getDBConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE `email` = :e OR `name` = :n LIMIT 1");
                $stmt->execute([':e' => $email, ':n' => $email]);
                $adm = $stmt->fetch();
                if ($adm && password_verify($password, $adm['password'])) {
                    $valid = true;
                }
            }
        } catch (Exception $e) {}

        if (!$valid && (($email === 'admin@nex47.com' || $email === 'admin') && $password === 'samsparrow')) {
            $valid = true;
        }

        if ($valid) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_email'] = 'admin@nex47.com';
            $_SESSION['admin_name'] = 'NEX-47 SuperAdmin';
            header('Location: /?admin=dashboard');
            exit;
        } else {
            $loginError = 'Access Denied: Incorrect password or username.';
        }
    }

    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        // Render Dashboard
        $totalLeads = 0; $newLeads = 0; $progressLeads = 0; $convertedLeads = 0;
        try {
            $pdo = getDBConnection();
            if ($pdo) {
                $totalLeads = $pdo->query("SELECT COUNT(*) FROM `leads`")->fetchColumn() ?: 0;
                $newLeads = $pdo->query("SELECT COUNT(*) FROM `leads` WHERE `status` = 'new'")->fetchColumn() ?: 0;
                $progressLeads = $pdo->query("SELECT COUNT(*) FROM `leads` WHERE `status` = 'contacted' OR `status` = 'in_progress'")->fetchColumn() ?: 0;
                $convertedLeads = $pdo->query("SELECT COUNT(*) FROM `leads` WHERE `status` = 'converted'")->fetchColumn() ?: 0;
            }
        } catch (Exception $e) {}
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NEX - 47 CATALYS'S | Admin Lead Control & CRM</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
    body { background-color: #060608; color: #ffffff; font-family: 'Inter', sans-serif; }
    .neon-text { color: #39ff14; text-shadow: 0 0 14px rgba(57,255,20,0.4); }
    .glass-panel { background: rgba(14, 15, 23, 0.85); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
  </style>
</head>
<body class="min-h-screen flex flex-col selection:bg-[#39ff14] selection:text-black">
  <header class="border-b border-white/10 bg-[#090a0f]/90 sticky top-0 z-30 px-6 py-4">
    <div class="max-w-7xl mx-auto flex justify-between items-center flex-wrap gap-4">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-black border border-[#39ff14]/50 p-1 flex items-center justify-center">
          <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="w-full h-full"><polygon points="30,20 80,20 170,180 120,180" fill="#39ff14"/><polygon points="170,20 120,20 30,180 80,180" fill="#39ff14"/></svg>
        </div>
        <div>
          <div class="font-['Space_Grotesk'] font-extrabold text-lg text-white flex items-center gap-1.5">
            NEX <span class="text-[#39ff14]">- 47</span>
            <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded bg-[#39ff14]/15 text-[#39ff14] border border-[#39ff14]/30 ml-2">CRM ENGINE</span>
          </div>
          <div class="text-[10px] font-mono text-gray-400">LEAD CAPTURE & CLIENT PIPELINE</div>
        </div>
      </div>
      <div class="flex items-center gap-3">
        <a href="/api/export_leads.php" class="px-3.5 py-2 rounded-lg bg-white/5 border border-white/15 text-xs font-mono text-gray-200 hover:border-[#39ff14] flex items-center gap-2">
          <i class="fa-solid fa-file-csv text-[#39ff14]"></i> Export CSV
        </a>
        <a href="/" target="_blank" class="px-3.5 py-2 rounded-lg bg-[#39ff14] text-[#060608] text-xs font-mono font-bold hover:bg-white flex items-center gap-2">
          <i class="fa-solid fa-arrow-up-right-from-square"></i> Live Site
        </a>
        <a href="/?admin=logout" class="px-3 py-2 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 hover:bg-red-500 hover:text-white text-xs font-mono flex items-center gap-1.5">
          <i class="fa-solid fa-right-to-bracket"></i> Logout
        </a>
      </div>
    </div>
  </header>
  <main class="max-w-7xl mx-auto px-6 py-8 flex-1 w-full space-y-8">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="glass-panel rounded-xl p-5 border-l-4 border-l-[#39ff14]">
        <div class="text-xs font-mono uppercase text-gray-400">Total Inquiries</div>
        <div class="font-['Space_Grotesk'] font-extrabold text-3xl text-white mt-1"><?php echo number_format($totalLeads); ?></div>
        <div class="text-[11px] text-[#39ff14] mt-2 flex items-center gap-1"><i class="fa-solid fa-database"></i> Stored in Database</div>
      </div>
      <div class="glass-panel rounded-xl p-5 border-l-4 border-l-yellow-400">
        <div class="text-xs font-mono uppercase text-gray-400">New / Uncontacted</div>
        <div class="font-['Space_Grotesk'] font-extrabold text-3xl text-yellow-400 mt-1"><?php echo number_format($newLeads); ?></div>
        <div class="text-[11px] text-gray-400 mt-2 flex items-center gap-1"><i class="fa-solid fa-bolt text-yellow-400"></i> WhatsApp follow-up</div>
      </div>
      <div class="glass-panel rounded-xl p-5 border-l-4 border-l-[#00f0ff]">
        <div class="text-xs font-mono uppercase text-gray-400">In Progress / Contacted</div>
        <div class="font-['Space_Grotesk'] font-extrabold text-3xl text-[#00f0ff] mt-1"><?php echo number_format($progressLeads); ?></div>
        <div class="text-[11px] text-gray-400 mt-2 flex items-center gap-1"><i class="fa-solid fa-comments text-[#00f0ff]"></i> Call Scheduled</div>
      </div>
      <div class="glass-panel rounded-xl p-5 border-l-4 border-l-emerald-500">
        <div class="text-xs font-mono uppercase text-gray-400">Converted Clients</div>
        <div class="font-['Space_Grotesk'] font-extrabold text-3xl text-emerald-400 mt-1"><?php echo number_format($convertedLeads); ?></div>
        <div class="text-[11px] text-emerald-400 mt-2 flex items-center gap-1"><i class="fa-solid fa-circle-check"></i> Active Retainers</div>
      </div>
    </div>
    <div class="glass-panel rounded-xl border border-white/10 overflow-hidden">
      <div class="p-5 border-b border-white/10 flex flex-wrap justify-between items-center gap-4 bg-[#0a0b12]">
        <div class="flex items-center gap-3">
          <span class="w-2.5 h-2.5 rounded-full bg-[#39ff14] animate-pulse"></span>
          <h2 class="font-['Space_Grotesk'] font-bold text-lg text-white">Live Inbound Leads</h2>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
          <input type="text" id="searchInput" placeholder="Search name, phone, brand..." class="bg-[#12131d] border border-white/15 rounded-lg pl-3 pr-3 py-1.5 text-xs text-white placeholder-gray-500 outline-none focus:border-[#39ff14] w-56" />
          <select id="statusFilter" class="bg-[#12131d] border border-white/15 rounded-lg px-3 py-1.5 text-xs text-gray-300 outline-none focus:border-[#39ff14]">
            <option value="all">All Statuses</option>
            <option value="new">New Inquiries</option>
            <option value="contacted">Contacted</option>
            <option value="in_progress">In Progress</option>
            <option value="converted">Converted</option>
            <option value="closed">Closed</option>
          </select>
          <button onclick="loadLeads()" class="p-2 rounded-lg bg-white/5 border border-white/15 text-gray-300 hover:text-[#39ff14]"><i class="fa-solid fa-rotate"></i></button>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs text-gray-300">
          <thead class="bg-[#0e0f18] text-gray-400 uppercase font-mono text-[10.5px] border-b border-white/10">
            <tr>
              <th class="py-3.5 px-4">#ID</th>
              <th class="py-3.5 px-4">Client / Brand</th>
              <th class="py-3.5 px-4">WhatsApp</th>
              <th class="py-3.5 px-4">Service</th>
              <th class="py-3.5 px-4">Budget</th>
              <th class="py-3.5 px-4">Status</th>
              <th class="py-3.5 px-4">Date</th>
            </tr>
          </thead>
          <tbody id="leadsTableBody" class="divide-y divide-white/5">
            <tr><td colspan="7" class="text-center py-8 text-gray-500 font-mono"><i class="fa-solid fa-circle-notch fa-spin text-lg text-[#39ff14]"></i> Loading records...</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </main>
  <script>
    async function loadLeads() {
      const search = document.getElementById('searchInput').value;
      const status = document.getElementById('statusFilter').value;
      try {
        const res = await fetch(`/api/get_leads.php?status=${status}&search=${encodeURIComponent(search)}`);
        const data = await res.json();
        if (data.success) renderTable(data.leads);
      } catch (err) { console.error(err); }
    }
    function renderTable(leads) {
      const tbody = document.getElementById('leadsTableBody');
      if (!leads || leads.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-gray-500 font-mono">No inquiries found.</td></tr>`;
        return;
      }
      tbody.innerHTML = leads.map(l => {
        const cleanPhone = (l.client_phone || '').replace(/[^0-9]/g, '');
        const waLink = `https://wa.me/${cleanPhone}`;
        return `
          <tr class="hover:bg-white/[0.02]">
            <td class="py-3 px-4 font-mono text-gray-500">#${l.id}</td>
            <td class="py-3 px-4"><div class="font-semibold text-white">${l.client_name}</div><div class="text-[11px] text-gray-400">${l.brand_name || 'N/A'}</div></td>
            <td class="py-3 px-4 font-mono"><a href="${waLink}" target="_blank" class="text-[#25d366] hover:underline"><i class="fa-brands fa-whatsapp"></i> ${l.client_phone}</a></td>
            <td class="py-3 px-4 text-gray-200">${l.service_type}</td>
            <td class="py-3 px-4 font-mono text-[#39ff14]">${l.budget || '$100 - $300'}</td>
            <td class="py-3 px-4"><span class="px-2 py-0.5 rounded text-[10px] uppercase font-mono border border-white/20">${l.status}</span></td>
            <td class="py-3 px-4 text-gray-400 text-[11px] font-mono">${new Date(l.created_at).toLocaleDateString()}</td>
          </tr>
        `;
      }).join('');
    }
    document.getElementById('searchInput').addEventListener('input', loadLeads);
    document.getElementById('statusFilter').addEventListener('change', loadLeads);
    loadLeads();
  </script>
</body>
</html>
        <?php
        exit;
    }

    // Render Login Gate
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NEX - 47 CATALYS'S | Admin Security Gate</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <style>
    body { background-color: #060608; color: #ffffff; font-family: 'Inter', sans-serif; }
    .cyber-grid { background-image: linear-gradient(to right, rgba(57, 255, 20, 0.03) 1px, transparent 1px), linear-gradient(to bottom, rgba(57, 255, 20, 0.03) 1px, transparent 1px); background-size: 32px 32px; }
    .neon-text { color: #39ff14; text-shadow: 0 0 16px rgba(57,255,20,0.5); }
    .glow-box { box-shadow: 0 0 45px rgba(57, 255, 20, 0.2), inset 0 0 15px rgba(57, 255, 20, 0.05); }
    .btn-neon { background: #39ff14; color: #060608; font-weight: 700; transition: all 0.3s ease; box-shadow: 0 0 20px rgba(57, 255, 20, 0.35); }
    .btn-neon:hover { background: #ffffff; color: #060608; box-shadow: 0 0 30px rgba(255, 255, 255, 0.5); transform: translateY(-2px); }
  </style>
</head>
<body class="min-h-screen cyber-grid flex items-center justify-center p-4 relative overflow-hidden">
  <div class="pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] rounded-full bg-[#39ff14]/10 blur-[140px]"></div>
  <div class="max-w-md w-full relative z-10">
    <div class="bg-[#0c0d14]/90 border-2 border-[#39ff14]/40 rounded-2xl p-8 sm:p-10 backdrop-blur-xl glow-box">
      <div class="text-center">
        <div class="w-16 h-16 rounded-2xl bg-black border border-[#39ff14] mx-auto flex items-center justify-center p-2 mb-4 shadow-[0_0_25px_rgba(57,255,20,0.35)]">
          <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="w-full h-full"><polygon points="30,20 80,20 170,180 120,180" fill="#39ff14"/><polygon points="170,20 120,20 30,180 80,180" fill="#39ff14"/></svg>
        </div>
        <h1 class="font-['Space_Grotesk'] font-extrabold text-2xl text-white tracking-wider">
          NEX <span class="neon-text">- 47</span>
        </h1>
        <div class="font-mono text-[10px] uppercase tracking-[0.3em] text-[#39ff14] mt-0.5">CRM CONTROL CENTER</div>
      </div>

      <?php if (!empty($loginError)): ?>
        <div class="mt-6 p-3.5 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-xs flex items-center gap-2.5">
          <i class="fa-solid fa-triangle-exclamation text-base"></i>
          <span><?php echo htmlspecialchars($loginError); ?></span>
        </div>
      <?php endif; ?>

      <?php if (!empty($logoutMsg)): ?>
        <div class="mt-6 p-3.5 rounded-xl bg-green-500/10 border border-green-500/30 text-[#39ff14] text-xs flex items-center gap-2.5">
          <i class="fa-solid fa-circle-check text-base"></i>
          <span><?php echo htmlspecialchars($logoutMsg); ?></span>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" class="mt-6 space-y-4">
        <div>
          <label class="block text-xs font-mono uppercase text-gray-400 mb-1.5">Admin Email / Username</label>
          <div class="relative">
            <i class="fa-solid fa-user absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-500 text-xs"></i>
            <input type="text" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required placeholder="Enter your admin email or username" class="w-full bg-[#12131d] border border-white/15 rounded-xl pl-9 pr-4 py-3 text-sm text-white placeholder-gray-600 focus:border-[#39ff14] outline-none transition-colors" autocomplete="username" />
          </div>
        </div>
        <div>
          <div class="flex justify-between items-center mb-1.5">
            <label class="text-xs font-mono uppercase text-gray-400">Security Password</label>
          </div>
          <div class="relative">
            <i class="fa-solid fa-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-500 text-xs"></i>
            <input type="password" id="passwordInput" name="admin_pass_key" value="" required placeholder="Enter your secure password" class="w-full bg-[#12131d] border border-white/15 rounded-xl pl-9 pr-10 py-3 text-sm text-white placeholder-gray-600 focus:border-[#39ff14] outline-none transition-colors font-mono" autocomplete="new-password" />
            <button type="button" id="togglePasswordBtn" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-[#39ff14]">
              <i class="fa-solid fa-eye text-xs"></i>
            </button>
          </div>
        </div>
        <div class="flex items-center justify-between text-xs text-gray-400 pt-1">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="remember" class="rounded bg-[#12131d] border-white/20 text-[#39ff14] focus:ring-0" />
            <span>Remember device</span>
          </label>
          <span class="text-[#39ff14] font-mono text-[11px]"><i class="fa-solid fa-shield-halved"></i> 256-Bit SSL</span>
        </div>
        <button type="submit" class="btn-neon w-full py-3.5 rounded-xl text-xs uppercase font-mono tracking-wider flex items-center justify-center gap-2 mt-4">
          <i class="fa-solid fa-right-to-bracket"></i>
          <span>Authenticate & Access</span>
        </button>
      </form>
    </div>
    <div class="text-center mt-6">
      <a href="/" class="text-xs font-mono text-gray-400 hover:text-[#39ff14] flex items-center justify-center gap-2 transition-colors">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Return to Public Website</span>
      </a>
    </div>
  </div>
  <script>
    const passwordInput = document.getElementById('passwordInput');
    const togglePasswordBtn = document.getElementById('togglePasswordBtn');
    window.addEventListener('load', () => { if (passwordInput) passwordInput.value = ''; });
    togglePasswordBtn.addEventListener('click', () => {
      const isPassword = passwordInput.type === 'password';
      passwordInput.type = isPassword ? 'text' : 'password';
      togglePasswordBtn.innerHTML = isPassword ? '<i class="fa-solid fa-eye-slash text-xs"></i>' : '<i class="fa-solid fa-eye text-xs"></i>';
    });
  </script>
</body>
</html>
    <?php
    exit;
}

// ─── 6. DEFAULT: RENDER PUBLIC HOMEPAGE ─────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NEX - 47 CATALYS'S | Digital Marketing & Performance Growth Agency</title>
  <meta name="description" content="NEX - 47 CATALYS'S is a premier digital marketing agency specializing in Meta Ads, Google PPC, SEO, SMM, Lead Generation, Content Marketing, and E-Commerce Scale." />

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            pitch: '#060608',
            surface: '#0c0d12',
            card: '#11121a',
            'card-hover': '#161822',
            neon: '#39ff14',
            'neon-glow': 'rgba(57, 255, 20, 0.4)',
            'neon-lime': '#cbfb45',
            'neon-cyan': '#00f0ff',
            subtext: '#9ba3b4'
          },
          fontFamily: {
            heading: ['"Space Grotesk"', 'sans-serif'],
            body: ['"Inter"', 'sans-serif'],
            mono: ['"JetBrains Mono"', 'monospace']
          }
        }
      }
    }
  </script>

  <!-- Font Awesome 6 Icons CDN -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

  <!-- Custom Stylesheet -->
    <!-- Inline Critical Styling & Cyber Neon Theme -->
  <style>
/* ==========================================================================
   NEX - 47 CATALYS'S - Core Design System & Styling
   Neon Cyberpunk & Luxury Obsidian Agency Aesthetic
   ========================================================================== */

:root {
  --bg-pitch: #060608;
  --bg-surface: #0c0d12;
  --bg-card: #11121a;
  --bg-card-hover: #161822;
  --bg-card-border: rgba(57, 255, 20, 0.15);
  --bg-card-border-hover: rgba(57, 255, 20, 0.45);
  
  --neon-green: #39ff14;
  --neon-green-glow: rgba(57, 255, 20, 0.35);
  --neon-lime: #cbfb45;
  --neon-cyan: #00f0ff;
  --neon-emerald: #10b981;
  
  --text-main: #ffffff;
  --text-muted: #9ba3b4;
  --text-sub: #64748b;
  
  --font-heading: 'Space Grotesk', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  --font-body: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  --font-mono: 'JetBrains Mono', 'Fira Code', monospace;
}

/* Global Reset & Base */
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html {
  scroll-behavior: smooth;
  font-size: 16px;
  background-color: var(--bg-pitch);
  color: var(--text-main);
  font-family: var(--font-body);
  overflow-x: hidden;
  max-width: 100vw;
}

body {
  background-color: var(--bg-pitch);
  color: var(--text-main);
  line-height: 1.6;
  overflow-x: hidden;
  max-width: 100vw;
  position: relative;
  -webkit-font-smoothing: antialiased;
}

/* Background Cyber Grid */
.cyber-grid {
  background-image: 
    linear-gradient(to right, rgba(255, 255, 255, 0.025) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(255, 255, 255, 0.025) 1px, transparent 1px);
  background-size: 48px 48px;
}

.cyber-grid-dense {
  background-image: 
    linear-gradient(to right, rgba(57, 255, 20, 0.04) 1px, transparent 1px),
    linear-gradient(to bottom, rgba(57, 255, 20, 0.04) 1px, transparent 1px);
  background-size: 24px 24px;
}

/* Custom Scrollbar */
::-webkit-scrollbar {
  width: 8px;
}
::-webkit-scrollbar-track {
  background: var(--bg-pitch);
}
::-webkit-scrollbar-thumb {
  background: #1e2430;
  border-radius: 4px;
  border: 1px solid rgba(57, 255, 20, 0.2);
}
::-webkit-scrollbar-thumb:hover {
  background: var(--neon-green);
}

/* Glowing Neon Elements */
.glow-neon-text {
  text-shadow: 0 0 16px rgba(57, 255, 20, 0.6), 0 0 32px rgba(57, 255, 20, 0.2);
}

.glow-box-neon {
  box-shadow: 0 0 25px rgba(57, 255, 20, 0.2), inset 0 0 15px rgba(57, 255, 20, 0.05);
}

.glow-box-neon-strong {
  box-shadow: 0 0 35px rgba(57, 255, 20, 0.4), inset 0 0 20px rgba(57, 255, 20, 0.15);
}

.glow-cyan-text {
  text-shadow: 0 0 16px rgba(0, 240, 255, 0.6);
}

/* Buttons */
.btn-neon {
  background: var(--neon-green);
  color: #050507;
  font-weight: 700;
  padding: 12px 28px;
  border-radius: 9999px;
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  text-decoration: none;
  border: 1px solid var(--neon-green);
  box-shadow: 0 0 20px rgba(57, 255, 20, 0.35);
  cursor: pointer;
  white-space: nowrap;
}

.btn-neon:hover {
  background: #ffffff;
  color: #050507;
  border-color: #ffffff;
  transform: translateY(-2px);
  box-shadow: 0 0 30px rgba(255, 255, 255, 0.5), 0 0 50px rgba(57, 255, 20, 0.4);
}

.btn-ghost-neon {
  background: rgba(57, 255, 20, 0.05);
  color: #ffffff;
  font-weight: 600;
  padding: 12px 26px;
  border-radius: 9999px;
  border: 1px solid rgba(57, 255, 20, 0.3);
  transition: all 0.3s ease;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  text-decoration: none;
  cursor: pointer;
  white-space: nowrap;
}

.btn-ghost-neon:hover {
  background: rgba(57, 255, 20, 0.15);
  border-color: var(--neon-green);
  color: var(--neon-green);
  transform: translateY(-2px);
  box-shadow: 0 0 25px rgba(57, 255, 20, 0.25);
}

/* Glassmorphism Card */
.glass-card {
  background: rgba(17, 18, 26, 0.75);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.glass-card:hover {
  border-color: rgba(57, 255, 20, 0.4);
  transform: translateY(-4px);
  box-shadow: 0 12px 32px -8px rgba(0, 0, 0, 0.8), 0 0 24px rgba(57, 255, 20, 0.15);
}

/* Digital Otters Style Service Card */
.service-split-box {
  border: 1px solid rgba(255, 255, 255, 0.12);
  background: #0d0e14;
  border-radius: 14px;
  overflow: hidden;
  transition: border-color 0.3s ease;
}

.service-split-box:hover {
  border-color: rgba(57, 255, 20, 0.5);
}

/* Service Pill / Chip */
.service-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  border-radius: 9999px;
  font-size: 0.8rem;
  font-weight: 500;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: #cbd5e1;
  transition: all 0.2s ease;
  text-decoration: none;
}

.service-chip:hover {
  background: rgba(57, 255, 20, 0.12);
  border-color: var(--neon-green);
  color: var(--neon-green);
  transform: translateY(-1px);
}

/* Monospace Badge */
.mono-tag {
  font-family: var(--font-mono);
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  padding: 4px 10px;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.mono-tag-green {
  background: rgba(57, 255, 20, 0.08);
  border: 1px solid rgba(57, 255, 20, 0.3);
  color: var(--neon-green);
}

.mono-tag-cyan {
  background: rgba(0, 240, 255, 0.08);
  border: 1px solid rgba(0, 240, 255, 0.3);
  color: var(--neon-cyan);
}

/* Pulse animation */
@keyframes neonPulse {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.6;
    transform: scale(0.96);
  }
}

.pulse-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: var(--neon-green);
  box-shadow: 0 0 10px var(--neon-green);
  animation: neonPulse 2s infinite ease-in-out;
}

/* Interactive Slider Custom Styling */
input[type="range"] {
  -webkit-appearance: none;
  width: 100%;
  height: 8px;
  border-radius: 4px;
  background: #1a1c26;
  outline: none;
  border: 1px solid rgba(57, 255, 20, 0.2);
}

input[type="range"]::-webkit-slider-thumb {
  -webkit-appearance: none;
  appearance: none;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--neon-green);
  cursor: pointer;
  box-shadow: 0 0 15px var(--neon-green);
  border: 2px solid #060608;
  transition: transform 0.15s ease;
}

input[type="range"]::-webkit-slider-thumb:hover {
  transform: scale(1.2);
}

/* Floating WhatsApp Button */
.whatsapp-float {
  position: fixed;
  bottom: 24px;
  right: 24px;
  background: #25d366;
  color: white;
  width: 54px;
  height: 54px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 6px 24px rgba(37, 211, 102, 0.45);
  z-index: 99;
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  text-decoration: none;
}

.whatsapp-float:hover {
  transform: scale(1.1) translateY(-3px);
  box-shadow: 0 10px 30px rgba(37, 211, 102, 0.65);
}

/* Modal Backdrop */
.modal-overlay {
  background: rgba(4, 5, 8, 0.85);
  backdrop-filter: blur(10px);
}

/* Mobile Specific Responsive Tweaks */
@media (max-width: 640px) {
  html, body {
    font-size: 14.5px;
  }
  .btn-neon, .btn-ghost-neon {
    width: 100%;
    padding: 11px 20px;
    font-size: 0.8rem;
  }
  .service-chip {
    font-size: 0.74rem;
    padding: 5px 10px;
  }
  .glass-card {
    padding: 1.25rem !important;
  }
  .whatsapp-float {
    bottom: 18px;
    right: 18px;
    width: 48px;
    height: 48px;
  }
}

  </style>
</head>

<body class="bg-[#060608] text-white selection:bg-[#39ff14] selection:text-black">

  <!-- Top Cyber Status Bar -->
  <div class="border-b border-white/5 bg-[#090a0f] py-2 px-4 text-xs font-mono text-gray-400">
    <div class="max-w-7xl mx-auto flex justify-between items-center flex-wrap gap-2">
      <div class="flex items-center gap-3">
        <span class="flex items-center gap-2 text-[#39ff14]">
          <span class="pulse-dot"></span>
          <span>NEX-47 ENGINE: ONLINE</span>
        </span>
        <span class="hidden sm:inline text-gray-600">|</span>
        <span class="hidden sm:inline text-gray-400">ROI-Driven Growth · Performance Media · Full Funnel Scalability</span>
      </div>
      <div class="flex items-center gap-4">
        <a href="https://wa.me/923495438083" target="_blank" class="text-gray-300 hover:text-[#39ff14] flex items-center gap-1.5 transition-colors">
          <i class="fa-brands fa-whatsapp text-[#25d366]"></i>
          <span>Direct WhatsApp</span>
        </a>
      </div>
    </div>
  </div>

  <!-- Header / Navigation Bar -->
  <header id="mainHeader" class="sticky top-0 z-40 w-full transition-all duration-300 bg-[#060608]/95 backdrop-blur-md border-b border-white/10">
    <div class="max-w-7xl mx-auto px-5 sm:px-8 h-20 flex items-center justify-between">
      
      <!-- Brand Logo -->
      <a href="#hero" class="flex items-center gap-3 group">
        <div class="relative w-12 h-12 rounded-xl bg-black/80 border border-[#39ff14]/30 p-1 flex items-center justify-center overflow-hidden transition-all duration-300 group-hover:border-[#39ff14] group-hover:shadow-[0_0_20px_rgba(57,255,20,0.4)]">
          <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;filter:drop-shadow(0 0 10px #39ff14)"><polygon points="30,20 80,20 170,180 120,180" fill="#39ff14"/><polygon points="170,20 120,20 30,180 80,180" fill="#39ff14"/></svg>
        </div>
        <div>
          <div class="font-heading font-extrabold text-lg tracking-wider text-white flex items-center gap-1">
            NEX <span class="text-[#39ff14]">- 47</span>
          </div>
          <div class="font-mono text-[9px] uppercase tracking-[0.25em] text-[#39ff14]/80">CATALYS'S</div>
        </div>
      </a>

      <!-- Desktop Nav -->
      <nav class="hidden lg:flex items-center gap-1 bg-[#11121a]/80 backdrop-blur-md px-4 py-2 rounded-full border border-white/10">
        <a href="#services" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-[#39ff14] transition-colors rounded-full hover:bg-white/5">Services</a>
        <a href="#calculator" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-[#39ff14] transition-colors rounded-full hover:bg-white/5">ROI Calculator</a>
        <a href="#process" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-[#39ff14] transition-colors rounded-full hover:bg-white/5">Our Process</a>
        <a href="#results" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-[#39ff14] transition-colors rounded-full hover:bg-white/5">Case Studies</a>
        <a href="#faq" class="px-4 py-2 text-sm font-medium text-gray-300 hover:text-[#39ff14] transition-colors rounded-full hover:bg-white/5">FAQ</a>
      </nav>

      <!-- Right Action CTA -->
      <div class="hidden sm:flex items-center gap-3">
        <button class="open-consultation-modal btn-neon text-xs uppercase tracking-wider">
          <i class="fa-solid fa-bolt"></i>
          <span>Get Growth Plan</span>
        </button>
      </div>

      <!-- Mobile Menu Button -->
      <button id="mobileMenuBtn" class="lg:hidden p-2 rounded-lg bg-white/5 border border-white/10 text-white hover:text-[#39ff14] transition-colors">
        <i class="fa-solid fa-bars text-xl"></i>
      </button>
    </div>
  </header>

  <!-- Mobile Menu Drawer -->
  <div id="mobileDrawer" class="fixed inset-0 z-[999] bg-black/95 backdrop-blur-xl transform translate-x-full transition-transform duration-300 flex flex-col justify-between p-6 lg:hidden">
    <div>
      <div class="flex items-center justify-between border-b border-white/10 pb-4">
        <div class="flex items-center gap-3">
          <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;filter:drop-shadow(0 0 10px #39ff14)"><polygon points="30,20 80,20 170,180 120,180" fill="#39ff14"/><polygon points="170,20 120,20 30,180 80,180" fill="#39ff14"/></svg>
          <span class="font-heading font-bold text-white">NEX - 47 <span class="text-[#39ff14]">CATALYS'S</span></span>
        </div>
        <button id="closeMobileMenu" class="p-2 text-gray-400 hover:text-white">
          <i class="fa-solid fa-xmark text-2xl"></i>
        </button>
      </div>
      <nav class="flex flex-col gap-4 mt-8">
        <a href="#services" class="mobile-nav-link text-lg font-medium text-gray-300 hover:text-[#39ff14]">9 Core Services</a>
        <a href="#calculator" class="mobile-nav-link text-lg font-medium text-gray-300 hover:text-[#39ff14]">Interactive ROI Calculator</a>
        <a href="#process" class="mobile-nav-link text-lg font-medium text-gray-300 hover:text-[#39ff14]">Catalyst Process</a>
        <a href="#results" class="mobile-nav-link text-lg font-medium text-gray-300 hover:text-[#39ff14]">Case Studies & Results</a>
        <a href="#faq" class="mobile-nav-link text-lg font-medium text-gray-300 hover:text-[#39ff14]">FAQ</a>
        <a href="#contact" class="mobile-nav-link text-lg font-medium text-gray-300 hover:text-[#39ff14]">Contact Us</a>
      </nav>
    </div>
    <div class="pt-6 border-t border-white/10 flex flex-col gap-3">
      <button class="open-consultation-modal btn-neon w-full justify-center">
        <span>Get Your Growth Plan</span>
        <i class="fa-solid fa-arrow-right"></i>
      </button>
      <a href="https://wa.me/923495438083" target="_blank" class="btn-ghost-neon w-full justify-center text-sm">
        <i class="fa-brands fa-whatsapp text-[#25d366]"></i>
        <span>Chat on WhatsApp</span>
      </a>
    </div>
  </div>

  <!-- Hero Section -->
  <section id="hero" class="relative pt-12 pb-16 sm:pt-20 sm:pb-24 overflow-hidden cyber-grid border-b border-white/10">
    <!-- Neon Radial Glow Background -->
    <div class="pointer-events-none absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[700px] rounded-full bg-[#39ff14]/10 blur-[130px]"></div>
    <div class="pointer-events-none absolute top-1/2 right-10 w-[400px] h-[400px] rounded-full bg-[#00f0ff]/5 blur-[120px]"></div>

    <div class="max-w-7xl mx-auto px-5 sm:px-8 relative z-10">
      
      <!-- Top Tag -->
      <div class="flex justify-center">
        <div class="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full bg-[#11121a] border border-[#39ff14]/30 shadow-[0_0_15px_rgba(57,255,20,0.15)]">
          <span class="pulse-dot"></span>
          <span class="font-mono text-xs uppercase tracking-widest text-[#39ff14] font-semibold">
            NEX-47 DIGITAL ACCELERATION ENGINE
          </span>
        </div>
      </div>

      <!-- Main Headline -->
      <div class="text-center mt-8 max-w-4xl mx-auto">
        <h1 class="font-heading font-extrabold text-4xl sm:text-6xl lg:text-7xl leading-[1.05] tracking-tight text-white">
          Accelerating Digital Dominance With <span class="text-[#39ff14] glow-neon-text">Precision Marketing</span>
        </h1>
        <p class="mt-6 text-lg sm:text-xl text-gray-300 leading-relaxed max-w-3xl mx-auto font-normal">
          From high-converting <strong class="text-white">Meta & Google Ads</strong> to authority <strong class="text-white">SEO, Viral Content, Lead Funnels,</strong> and <strong class="text-white">E-Commerce Scale</strong> — we engineer hyper-targeted campaigns that generate predictable, massive ROI.
        </p>
      </div>

      <!-- Hero Action CTAs -->
      <div class="mt-10 flex flex-wrap justify-center items-center gap-4">
        <button class="open-consultation-modal btn-neon px-8 py-4 text-sm uppercase tracking-wider">
          <span>Get Your Custom Growth Plan</span>
          <i class="fa-solid fa-arrow-right"></i>
        </button>
        <a href="https://wa.me/923495438083?text=Hi%20NEX-47,%20I%20want%20to%20discuss%20scaling%20my%20business." target="_blank" class="btn-ghost-neon px-8 py-4 text-sm">
          <i class="fa-brands fa-whatsapp text-lg text-[#25d366]"></i>
          <span>Instant WhatsApp Strategy</span>
        </a>
      </div>

      <!-- Floating Stats Showcase Grid -->
      <div class="mt-16 grid grid-cols-2 md:grid-cols-4 gap-4 max-w-5xl mx-auto">
        <div class="glass-card p-6 text-center border-l-2 border-l-[#39ff14]">
          <div class="font-heading font-extrabold text-3xl sm:text-4xl text-[#39ff14]">+450%</div>
          <div class="text-xs uppercase font-mono tracking-wider text-gray-400 mt-1">Average Client ROAS</div>
        </div>
        <div class="glass-card p-6 text-center border-l-2 border-l-[#00f0ff]">
          <div class="font-heading font-extrabold text-3xl sm:text-4xl text-[#00f0ff]">500K+</div>
          <div class="text-xs uppercase font-mono tracking-wider text-gray-400 mt-1">Qualified Leads Generated</div>
        </div>
        <div class="glass-card p-6 text-center border-l-2 border-l-[#cbfb45]">
          <div class="font-heading font-extrabold text-3xl sm:text-4xl text-[#cbfb45]">$15M+</div>
          <div class="text-xs uppercase font-mono tracking-wider text-gray-400 mt-1">Tracked Client Revenue</div>
        </div>
        <div class="glass-card p-6 text-center border-l-2 border-l-[#39ff14]">
          <div class="font-heading font-extrabold text-3xl sm:text-4xl text-[#39ff14]">99.4%</div>
          <div class="text-xs uppercase font-mono tracking-wider text-gray-400 mt-1">Campaign Accuracy Rate</div>
        </div>
      </div>

      <!-- Brand Logo Hero Visual Strip -->
      <div class="mt-14 pt-8 border-t border-white/10 flex flex-wrap items-center justify-center gap-8 sm:gap-14 text-gray-400 font-mono text-xs uppercase tracking-widest">
        <span class="flex items-center gap-2"><i class="fa-brands fa-meta text-[#39ff14]"></i> Meta Verified Ads</span>
        <span class="flex items-center gap-2"><i class="fa-brands fa-google text-[#00f0ff]"></i> Google Premier Partner</span>
        <span class="flex items-center gap-2"><i class="fa-brands fa-tiktok text-white"></i> TikTok For Business</span>
        <span class="flex items-center gap-2"><i class="fa-brands fa-shopify text-[#95bf47]"></i> Shopify Plus Scale</span>
      </div>

    </div>
  </section>

  <!-- 9 Core Services Master Section (Digital Otters Structure) -->
  <section id="services" class="py-24 bg-[#08090e] relative border-b border-white/10">
    <div class="max-w-7xl mx-auto px-5 sm:px-8">
      
      <!-- Section Header -->
      <div class="text-center max-w-3xl mx-auto">
        <div class="mono-tag mono-tag-green">
          <i class="fa-solid fa-layer-group"></i> OUR 9 CORE CAPABILITIES
        </div>
        <h2 class="font-heading font-extrabold text-3xl sm:text-5xl mt-4 text-white tracking-tight">
          Complete Full-Funnel <span class="text-[#39ff14]">Marketing Services</span>
        </h2>
        <p class="text-gray-400 mt-3 text-base">
          From customer acquisition to retargeting, viral brand presence, and automated analytics — every single service engineered for ruthless conversion.
        </p>
      </div>

      <!-- Service Category Filter Buttons -->
      <div class="mt-10 flex flex-wrap justify-center gap-2 max-w-4xl mx-auto">
        <button class="service-filter-btn px-5 py-2 rounded-full text-xs font-mono uppercase tracking-wider bg-[#39ff14] text-[#060608] font-bold border border-[#39ff14] transition-all" data-filter="all">All 9 Services</button>
        <button class="service-filter-btn px-5 py-2 rounded-full text-xs font-mono uppercase tracking-wider bg-white/5 text-white/70 border border-white/10 hover:border-[#39ff14] transition-all" data-filter="ads">Paid Ads & PPC</button>
        <button class="service-filter-btn px-5 py-2 rounded-full text-xs font-mono uppercase tracking-wider bg-white/5 text-white/70 border border-white/10 hover:border-[#39ff14] transition-all" data-filter="organic">Organic & SEO</button>
        <button class="service-filter-btn px-5 py-2 rounded-full text-xs font-mono uppercase tracking-wider bg-white/5 text-white/70 border border-white/10 hover:border-[#39ff14] transition-all" data-filter="content">Social & Content</button>
        <button class="service-filter-btn px-5 py-2 rounded-full text-xs font-mono uppercase tracking-wider bg-white/5 text-white/70 border border-white/10 hover:border-[#39ff14] transition-all" data-filter="funnels">Funnels & Tracking</button>
      </div>

      <!-- Services Split Cards List (01 to 09) -->
      <div class="mt-14 space-y-12">

        <!-- 01: Social Media Marketing (SMM) -->
        <div class="service-card-item service-split-box p-6 sm:p-8" data-category="content all">
          <div class="grid lg:grid-cols-[1.1fr_0.9fr_1fr] gap-8 items-stretch">
            <!-- Left Info -->
            <div class="flex flex-col justify-between">
              <div>
                <div class="flex items-center gap-3">
                  <span class="font-heading font-extrabold text-4xl text-[#39ff14]/30">01</span>
                  <span class="mono-tag mono-tag-green">ORGANIC REACH & ENGAGEMENT</span>
                </div>
                <h3 class="font-heading font-bold text-2xl sm:text-3xl text-white mt-2">
                  Social Media Marketing (SMM)
                </h3>
                <p class="text-gray-300 text-sm mt-3 leading-relaxed">
                  We build and manage active, high-retention communities across every major platform. We craft engaging brand narratives that turn casual scrollers into loyal customers and enthusiastic advocates.
                </p>
                <!-- Sub service chips -->
                <div class="mt-5 flex flex-wrap gap-2">
                  <span class="service-chip"><i class="fa-brands fa-facebook text-blue-500"></i> Facebook Marketing</span>
                  <span class="service-chip"><i class="fa-brands fa-instagram text-pink-500"></i> Instagram Growth</span>
                  <span class="service-chip"><i class="fa-brands fa-tiktok text-white"></i> TikTok Management</span>
                  <span class="service-chip"><i class="fa-brands fa-youtube text-red-500"></i> YouTube Strategy</span>
                  <span class="service-chip"><i class="fa-brands fa-linkedin text-blue-400"></i> LinkedIn B2B</span>
                  <span class="service-chip"><i class="fa-solid fa-comments text-[#39ff14]"></i> Daily Engagement & DM Management</span>
                </div>
              </div>
              <div class="mt-6 pt-4 border-t border-white/10">
                <button class="open-consultation-modal text-[#39ff14] text-xs font-mono uppercase tracking-wider flex items-center gap-2 hover:underline" data-service="Social Media Marketing (SMM)">
                  <span>Explore SMM Growth Blueprint</span>
                  <i class="fa-solid fa-arrow-right"></i>
                </button>
              </div>
            </div>

            <!-- Middle Visual Badge Box -->
            <div class="bg-[#13141f] rounded-xl p-6 border border-white/10 flex flex-col justify-center relative overflow-hidden">
              <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-[#39ff14]/10 rounded-full blur-2xl"></div>
              <div class="text-xs font-mono uppercase text-[#39ff14] tracking-widest mb-4">Omnichannel Platform Matrix</div>
              <div class="grid grid-cols-2 gap-3">
                <div class="bg-black/60 p-3 rounded-lg border border-white/5">
                  <div class="text-lg font-bold text-white">100% Custom</div>
                  <div class="text-[11px] text-gray-400">Branded Creatives</div>
                </div>
                <div class="bg-black/60 p-3 rounded-lg border border-white/5">
                  <div class="text-lg font-bold text-[#39ff14]">30+ Posts/Mo</div>
                  <div class="text-[11px] text-gray-400">Consistent Scheduling</div>
                </div>
                <div class="bg-black/60 p-3 rounded-lg border border-white/5">
                  <div class="text-lg font-bold text-[#00f0ff]">24/7 DMs</div>
                  <div class="text-[11px] text-gray-400">Community Support</div>
                </div>
                <div class="bg-black/60 p-3 rounded-lg border border-white/5">
                  <div class="text-lg font-bold text-[#cbfb45]">Viral Reels</div>
                  <div class="text-[11px] text-gray-400">High Organic Reach</div>
                </div>
              </div>
            </div>

            <!-- Right: What runs inside it -->
            <div class="bg-black/40 rounded-xl p-5 border border-white/10">
              <div class="font-mono text-xs text-[#39ff14] uppercase tracking-widest pb-3 border-b border-white/10">
                What Runs Inside It
              </div>
              <ul class="mt-4 space-y-3 text-xs text-gray-300">
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">01</span>
                  <span><strong>Content Pillars & Calendar:</strong> Structured weekly calendar aligned with sales goals.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">02</span>
                  <span><strong>Multi-Platform Publishing:</strong> Tailored captions, hashtags, and formatting for FB, IG, TT, YT & LI.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">03</span>
                  <span><strong>Audience Interaction:</strong> Replying to comments and active DM lead generation.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">04</span>
                  <span><strong>Monthly Growth Audits:</strong> In-depth breakdown of reach, follower growth, and click-throughs.</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- 02: Meta Ads / Facebook Ads -->
        <div class="service-card-item service-split-box p-6 sm:p-8" data-category="ads all">
          <div class="grid lg:grid-cols-[1.1fr_0.9fr_1fr] gap-8 items-stretch">
            <!-- Left Info -->
            <div class="flex flex-col justify-between">
              <div>
                <div class="flex items-center gap-3">
                  <span class="font-heading font-extrabold text-4xl text-[#39ff14]/30">02</span>
                  <span class="mono-tag mono-tag-green">PAID PERFORMANCE & SCALE</span>
                </div>
                <h3 class="font-heading font-bold text-2xl sm:text-3xl text-white mt-2">
                  Meta Ads / Facebook & Instagram Ads
                </h3>
                <p class="text-gray-300 text-sm mt-3 leading-relaxed">
                  Laser-targeted paid ad campaigns engineered to maximize ROAS, lower Cost-Per-Lead (CPL), and dominate competitor feeds with compelling UGC and high-converting hooks.
                </p>
                <!-- Sub service chips -->
                <div class="mt-5 flex flex-wrap gap-2">
                  <span class="service-chip"><i class="fa-solid fa-bullseye text-[#39ff14]"></i> Campaign Creation</span>
                  <span class="service-chip"><i class="fa-solid fa-crosshairs text-[#00f0ff]"></i> Objective Selection (Leads/Sales)</span>
                  <span class="service-chip"><i class="fa-solid fa-users text-purple-400"></i> Lookalike & Custom Audiences</span>
                  <span class="service-chip"><i class="fa-solid fa-arrows-spin text-yellow-400"></i> Dynamic Retargeting</span>
                  <span class="service-chip"><i class="fa-solid fa-chart-line text-[#39ff14]"></i> CTR & ROAS Optimization</span>
                  <span class="service-chip"><i class="fa-solid fa-filter-circle-dollar text-green-400"></i> CPL & CPC Reduction</span>
                </div>
              </div>
              <div class="mt-6 pt-4 border-t border-white/10">
                <button class="open-consultation-modal text-[#39ff14] text-xs font-mono uppercase tracking-wider flex items-center gap-2 hover:underline" data-service="Meta Ads / Facebook Ads">
                  <span>Scale Meta Campaigns With Us</span>
                  <i class="fa-solid fa-arrow-right"></i>
                </button>
              </div>
            </div>

            <!-- Middle Visual Badge Box -->
            <div class="bg-[#13141f] rounded-xl p-6 border border-white/10 flex flex-col justify-center relative overflow-hidden">
              <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-[#39ff14]/10 rounded-full blur-2xl"></div>
              <div class="text-xs font-mono uppercase text-[#39ff14] tracking-widest mb-4">Meta Performance KPI Benchmarks</div>
              <div class="space-y-3 text-xs">
                <div class="flex justify-between items-center bg-black/60 p-2.5 rounded-lg border border-white/5">
                  <span class="text-gray-400">Avg. Click-Through Rate (CTR):</span>
                  <span class="text-[#39ff14] font-bold">3.2% - 5.8%</span>
                </div>
                <div class="flex justify-between items-center bg-black/60 p-2.5 rounded-lg border border-white/5">
                  <span class="text-gray-400">Target ROAS Multiplier:</span>
                  <span class="text-[#00f0ff] font-bold">4.0x - 8.5x</span>
                </div>
                <div class="flex justify-between items-center bg-black/60 p-2.5 rounded-lg border border-white/5">
                  <span class="text-gray-400">Cost-Per-Lead Reduction:</span>
                  <span class="text-[#cbfb45] font-bold">Up to -45%</span>
                </div>
              </div>
            </div>

            <!-- Right: What runs inside it -->
            <div class="bg-black/40 rounded-xl p-5 border border-white/10">
              <div class="font-mono text-xs text-[#39ff14] uppercase tracking-widest pb-3 border-b border-white/10">
                What Runs Inside It
              </div>
              <ul class="mt-4 space-y-3 text-xs text-gray-300">
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">01</span>
                  <span><strong>Ad Account Structuring:</strong> Top of Funnel (TOF), Middle (MOF), and Bottom (BOF) separation.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">02</span>
                  <span><strong>Creative A/B Testing:</strong> 10+ variations of video hooks, headlines, and thumbnails tested per week.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">03</span>
                  <span><strong>Deep Audience Segmentation:</strong> Purchasing power, high-income zip codes, and competitor fans.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">04</span>
                  <span><strong>Budget Scaling Rules:</strong> Scaling winning ads vertically and horizontally without burning ROI.</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- 03: Google Ads (PPC) -->
        <div class="service-card-item service-split-box p-6 sm:p-8" data-category="ads all">
          <div class="grid lg:grid-cols-[1.1fr_0.9fr_1fr] gap-8 items-stretch">
            <!-- Left Info -->
            <div class="flex flex-col justify-between">
              <div>
                <div class="flex items-center gap-3">
                  <span class="font-heading font-extrabold text-4xl text-[#39ff14]/30">03</span>
                  <span class="mono-tag mono-tag-green">HIGH-INTENT SEARCH TRAFFIC</span>
                </div>
                <h3 class="font-heading font-bold text-2xl sm:text-3xl text-white mt-2">
                  Google Ads (PPC & Shopping)
                </h3>
                <p class="text-gray-300 text-sm mt-3 leading-relaxed">
                  Capture high-intent buyers the exact moment they search for your products or services. From Google Search & Shopping to YouTube video placements, we ensure you capture market share profitably.
                </p>
                <!-- Sub service chips -->
                <div class="mt-5 flex flex-wrap gap-2">
                  <span class="service-chip"><i class="fa-solid fa-magnifying-glass text-[#39ff14]"></i> Google Search Ads</span>
                  <span class="service-chip"><i class="fa-solid fa-image text-blue-400"></i> Google Display Network</span>
                  <span class="service-chip"><i class="fa-brands fa-youtube text-red-500"></i> YouTube In-Stream Ads</span>
                  <span class="service-chip"><i class="fa-solid fa-bag-shopping text-yellow-400"></i> Google Shopping Ads</span>
                  <span class="service-chip"><i class="fa-solid fa-key text-purple-400"></i> High-Intent Keywords</span>
                  <span class="service-chip"><i class="fa-solid fa-bullseye text-[#39ff14]"></i> Enhanced Conversion Tracking</span>
                </div>
              </div>
              <div class="mt-6 pt-4 border-t border-white/10">
                <button class="open-consultation-modal text-[#39ff14] text-xs font-mono uppercase tracking-wider flex items-center gap-2 hover:underline" data-service="Google Ads (PPC)">
                  <span>Launch High-Intent Google Ads</span>
                  <i class="fa-solid fa-arrow-right"></i>
                </button>
              </div>
            </div>

            <!-- Middle Visual Badge Box -->
            <div class="bg-[#13141f] rounded-xl p-6 border border-white/10 flex flex-col justify-center relative overflow-hidden">
              <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-[#00f0ff]/10 rounded-full blur-2xl"></div>
              <div class="text-xs font-mono uppercase text-[#00f0ff] tracking-widest mb-4">Search Engine Dominance</div>
              <div class="space-y-2.5 text-xs">
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex items-center gap-2">
                  <i class="fa-solid fa-check-circle text-[#39ff14]"></i>
                  <span>Zero-Wasted Ad Spend (Negative Keyword Lists)</span>
                </div>
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex items-center gap-2">
                  <i class="fa-solid fa-check-circle text-[#39ff14]"></i>
                  <span>High Quality Score (QS 8-10) Landing Pages</span>
                </div>
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex items-center gap-2">
                  <i class="fa-solid fa-check-circle text-[#39ff14]"></i>
                  <span>Performance Max (PMax) AI Optimization</span>
                </div>
              </div>
            </div>

            <!-- Right: What runs inside it -->
            <div class="bg-black/40 rounded-xl p-5 border border-white/10">
              <div class="font-mono text-xs text-[#39ff14] uppercase tracking-widest pb-3 border-b border-white/10">
                What Runs Inside It
              </div>
              <ul class="mt-4 space-y-3 text-xs text-gray-300">
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">01</span>
                  <span><strong>Competitor Spy & Keyword Audit:</strong> Identifying exact high-converting buyer search queries.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">02</span>
                  <span><strong>Ad Copy Engineering:</strong> High-CTR responsive search ads with dynamic sitelinks and callouts.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">03</span>
                  <span><strong>Smart Bidding Calibration:</strong> Target CPA & Target ROAS bidding setup for automated scale.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">04</span>
                  <span><strong>Conversion Tracking Setup:</strong> GA4 and Google Ads tags connected to ensure 100% attribution.</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- 04: SEO (Search Engine Optimization) -->
        <div class="service-card-item service-split-box p-6 sm:p-8" data-category="organic all">
          <div class="grid lg:grid-cols-[1.1fr_0.9fr_1fr] gap-8 items-stretch">
            <!-- Left Info -->
            <div class="flex flex-col justify-between">
              <div>
                <div class="flex items-center gap-3">
                  <span class="font-heading font-extrabold text-4xl text-[#39ff14]/30">04</span>
                  <span class="mono-tag mono-tag-green">ORGANIC DOMINANCE</span>
                </div>
                <h3 class="font-heading font-bold text-2xl sm:text-3xl text-white mt-2">
                  SEO (Search Engine Optimization)
                </h3>
                <p class="text-gray-300 text-sm mt-3 leading-relaxed">
                  Rank on Google's #1 page for lucrative commercial terms without paying for clicks forever. Our battle-tested white-hat SEO builds lasting authority and sustainable organic inbound pipelines.
                </p>
                <!-- Sub service chips -->
                <div class="mt-5 flex flex-wrap gap-2">
                  <span class="service-chip"><i class="fa-solid fa-magnifying-glass-chart text-[#39ff14]"></i> Keyword Research</span>
                  <span class="service-chip"><i class="fa-solid fa-file-code text-blue-400"></i> On-Page SEO & Metadata</span>
                  <span class="service-chip"><i class="fa-solid fa-link text-purple-400"></i> Off-Page SEO & High-DA Links</span>
                  <span class="service-chip"><i class="fa-solid fa-server text-yellow-400"></i> Technical SEO & Core Web Vitals</span>
                  <span class="service-chip"><i class="fa-solid fa-trophy text-[#39ff14]"></i> Google #1 Ranking Blueprint</span>
                </div>
              </div>
              <div class="mt-6 pt-4 border-t border-white/10">
                <button class="open-consultation-modal text-[#39ff14] text-xs font-mono uppercase tracking-wider flex items-center gap-2 hover:underline" data-service="SEO (Search Engine Optimization)">
                  <span>Claim Your Free SEO Audit</span>
                  <i class="fa-solid fa-arrow-right"></i>
                </button>
              </div>
            </div>

            <!-- Middle Visual Badge Box -->
            <div class="bg-[#13141f] rounded-xl p-6 border border-white/10 flex flex-col justify-center relative overflow-hidden">
              <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-[#39ff14]/10 rounded-full blur-2xl"></div>
              <div class="text-xs font-mono uppercase text-[#39ff14] tracking-widest mb-4">Rankings Engine Architecture</div>
              <div class="grid grid-cols-2 gap-3 text-center">
                <div class="bg-black/60 p-3 rounded-lg border border-white/5">
                  <div class="text-xl font-bold text-[#39ff14]">100%</div>
                  <div class="text-[11px] text-gray-400">White-Hat Methods</div>
                </div>
                <div class="bg-black/60 p-3 rounded-lg border border-white/5">
                  <div class="text-xl font-bold text-[#00f0ff]">Top 3</div>
                  <div class="text-[11px] text-gray-400">Target SERP Goals</div>
                </div>
                <div class="bg-black/60 p-3 rounded-lg border border-white/5">
                  <div class="text-xl font-bold text-white">&lt; 1.2s</div>
                  <div class="text-[11px] text-gray-400">Load Speed Optimization</div>
                </div>
                <div class="bg-black/60 p-3 rounded-lg border border-white/5">
                  <div class="text-xl font-bold text-[#cbfb45]">High DA</div>
                  <div class="text-[11px] text-gray-400">Editorial Backlinks</div>
                </div>
              </div>
            </div>

            <!-- Right: What runs inside it -->
            <div class="bg-black/40 rounded-xl p-5 border border-white/10">
              <div class="font-mono text-xs text-[#39ff14] uppercase tracking-widest pb-3 border-b border-white/10">
                What Runs Inside It
              </div>
              <ul class="mt-4 space-y-3 text-xs text-gray-300">
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">01</span>
                  <span><strong>Full Technical Crawl:</strong> Fixing indexing errors, broken redirects, schema markup, and speed issues.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">02</span>
                  <span><strong>Topic Clusters & Silo Architecture:</strong> Structuring content so search engines understand your authority.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">03</span>
                  <span><strong>High-Tier Link Building:</strong> Securing genuine editorial backlinks and niche digital PR mentions.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">04</span>
                  <span><strong>Rank Tracking & SERP Monitoring:</strong> Daily position tracking and competitor movement insights.</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- 05: Content Marketing -->
        <div class="service-card-item service-split-box p-6 sm:p-8" data-category="content all">
          <div class="grid lg:grid-cols-[1.1fr_0.9fr_1fr] gap-8 items-stretch">
            <!-- Left Info -->
            <div class="flex flex-col justify-between">
              <div>
                <div class="flex items-center gap-3">
                  <span class="font-heading font-extrabold text-4xl text-[#39ff14]/30">05</span>
                  <span class="mono-tag mono-tag-green">ATTENTION & CONVERSION</span>
                </div>
                <h3 class="font-heading font-bold text-2xl sm:text-3xl text-white mt-2">
                  Content Marketing & Production
                </h3>
                <p class="text-gray-300 text-sm mt-3 leading-relaxed">
                  Attention is the new currency. We create viral video reels, high-authority articles, persuasive ad copy, and strategic multi-format content designed to position you as the undisputed leader in your niche.
                </p>
                <!-- Sub service chips -->
                <div class="mt-5 flex flex-wrap gap-2">
                  <span class="service-chip"><i class="fa-solid fa-clapperboard text-[#39ff14]"></i> Viral Reels & Shorts</span>
                  <span class="service-chip"><i class="fa-solid fa-video text-red-400"></i> Video Editing & Motion Graphics</span>
                  <span class="service-chip"><i class="fa-solid fa-newspaper text-blue-400"></i> SEO Blog Articles</span>
                  <span class="service-chip"><i class="fa-solid fa-pen-nib text-purple-400"></i> High-Converting Copywriting</span>
                  <span class="service-chip"><i class="fa-solid fa-chess text-[#39ff14]"></i> End-to-End Content Strategy</span>
                </div>
              </div>
              <div class="mt-6 pt-4 border-t border-white/10">
                <button class="open-consultation-modal text-[#39ff14] text-xs font-mono uppercase tracking-wider flex items-center gap-2 hover:underline" data-service="Content Marketing">
                  <span>Start Creative Production</span>
                  <i class="fa-solid fa-arrow-right"></i>
                </button>
              </div>
            </div>

            <!-- Middle Visual Badge Box -->
            <div class="bg-[#13141f] rounded-xl p-6 border border-white/10 flex flex-col justify-center relative overflow-hidden">
              <div class="text-xs font-mono uppercase text-[#39ff14] tracking-widest mb-4">Content Production Engine</div>
              <div class="space-y-2 text-xs">
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex items-center justify-between">
                  <span class="text-gray-300">Short Form (Reels/TikTok):</span>
                  <span class="text-[#39ff14] font-bold">15 - 30 / Month</span>
                </div>
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex items-center justify-between">
                  <span class="text-gray-300">Sales Copywriting Hooks:</span>
                  <span class="text-[#00f0ff] font-bold">A/B Scripted</span>
                </div>
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex items-center justify-between">
                  <span class="text-gray-300">Long-Form Articles & SEO:</span>
                  <span class="text-[#cbfb45] font-bold">2,000+ Words</span>
                </div>
              </div>
            </div>

            <!-- Right: What runs inside it -->
            <div class="bg-black/40 rounded-xl p-5 border border-white/10">
              <div class="font-mono text-xs text-[#39ff14] uppercase tracking-widest pb-3 border-b border-white/10">
                What Runs Inside It
              </div>
              <ul class="mt-4 space-y-3 text-xs text-gray-300">
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">01</span>
                  <span><strong>Hook & Scriptwriting:</strong> Researching trending viral topics and writing retention-tested scripts.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">02</span>
                  <span><strong>High-Energy Editing:</strong> Dynamic subtitles, sound design, transitions, and branding.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">03</span>
                  <span><strong>Copywriting Frameworks:</strong> AIDA & PAS frameworks applied to ads, landing pages, and newsletters.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">04</span>
                  <span><strong>Distribution Strategy:</strong> Repurposing one core video into 6 distinct platform assets.</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- 06: Lead Generation & Funnels -->
        <div class="service-card-item service-split-box p-6 sm:p-8" data-category="funnels all">
          <div class="grid lg:grid-cols-[1.1fr_0.9fr_1fr] gap-8 items-stretch">
            <!-- Left Info -->
            <div class="flex flex-col justify-between">
              <div>
                <div class="flex items-center gap-3">
                  <span class="font-heading font-extrabold text-4xl text-[#39ff14]/30">06</span>
                  <span class="mono-tag mono-tag-green">HIGH-CONVERTING PIPELINES</span>
                </div>
                <h3 class="font-heading font-bold text-2xl sm:text-3xl text-white mt-2">
                  Lead Generation & Sales Funnels
                </h3>
                <p class="text-gray-300 text-sm mt-3 leading-relaxed">
                  Stop losing traffic to bad landing pages. We build automated lead capture systems, interactive quiz funnels, instant WhatsApp routing, and CRM pipelines that consistently deliver sales-qualified prospects.
                </p>
                <!-- Sub service chips -->
                <div class="mt-5 flex flex-wrap gap-2">
                  <span class="service-chip"><i class="fa-solid fa-filter text-[#39ff14]"></i> End-to-End Sales Funnels</span>
                  <span class="service-chip"><i class="fa-solid fa-laptop-code text-blue-400"></i> High-Converting Landing Pages</span>
                  <span class="service-chip"><i class="fa-solid fa-clipboard-list text-purple-400"></i> Interactive Lead Forms</span>
                  <span class="service-chip"><i class="fa-brands fa-whatsapp text-[#25d366]"></i> Instant WhatsApp Lead Funnels</span>
                  <span class="service-chip"><i class="fa-solid fa-gears text-[#39ff14]"></i> CRM & Email Automation</span>
                </div>
              </div>
              <div class="mt-6 pt-4 border-t border-white/10">
                <button class="open-consultation-modal text-[#39ff14] text-xs font-mono uppercase tracking-wider flex items-center gap-2 hover:underline" data-service="Lead Generation & Funnels">
                  <span>Build Your Automated Funnel</span>
                  <i class="fa-solid fa-arrow-right"></i>
                </button>
              </div>
            </div>

            <!-- Middle Visual Badge Box -->
            <div class="bg-[#13141f] rounded-xl p-6 border border-white/10 flex flex-col justify-center relative overflow-hidden">
              <div class="text-xs font-mono uppercase text-[#39ff14] tracking-widest mb-4">Funnel Conversion Stats</div>
              <div class="space-y-2.5 text-xs">
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex justify-between">
                  <span class="text-gray-400">Average Page Conversion:</span>
                  <span class="text-[#39ff14] font-bold">12% - 24%</span>
                </div>
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex justify-between">
                  <span class="text-gray-400">WhatsApp Lead Response:</span>
                  <span class="text-[#00f0ff] font-bold">&lt; 60 Seconds</span>
                </div>
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex justify-between">
                  <span class="text-gray-400">Lead Quality Filtering:</span>
                  <span class="text-[#cbfb45] font-bold">100% Qualified</span>
                </div>
              </div>
            </div>

            <!-- Right: What runs inside it -->
            <div class="bg-black/40 rounded-xl p-5 border border-white/10">
              <div class="font-mono text-xs text-[#39ff14] uppercase tracking-widest pb-3 border-b border-white/10">
                What Runs Inside It
              </div>
              <ul class="mt-4 space-y-3 text-xs text-gray-300">
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">01</span>
                  <span><strong>Landing Page Wireframing:</strong> Psychology-backed copywriting, clear CTAs, and frictionless forms.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">02</span>
                  <span><strong>Direct WhatsApp Integration:</strong> Pre-filled automated inquiries sent straight to your sales team.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">03</span>
                  <span><strong>CRM Lead Routing:</strong> Automated syncing with HubSpot, Zoho, Sheets, and email responders.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">04</span>
                  <span><strong>Continuous Heatmap & CRO Auditing:</strong> Hotjar tracking to remove drop-offs and maximize opt-ins.</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- 07: Analytics & Tracking -->
        <div class="service-card-item service-split-box p-6 sm:p-8" data-category="funnels all">
          <div class="grid lg:grid-cols-[1.1fr_0.9fr_1fr] gap-8 items-stretch">
            <!-- Left Info -->
            <div class="flex flex-col justify-between">
              <div>
                <div class="flex items-center gap-3">
                  <span class="font-heading font-extrabold text-4xl text-[#39ff14]/30">07</span>
                  <span class="mono-tag mono-tag-green">DATA-BACKED PRECISION</span>
                </div>
                <h3 class="font-heading font-bold text-2xl sm:text-3xl text-white mt-2">
                  Analytics, Tracking & Reporting
                </h3>
                <p class="text-gray-300 text-sm mt-3 leading-relaxed">
                  You cannot scale what you cannot measure. We configure enterprise-grade server-side tracking, Meta Conversion API (CAPI), and GA4 dashboards to ensure 100% accurate attribution for every dollar spent.
                </p>
                <!-- Sub service chips -->
                <div class="mt-5 flex flex-wrap gap-2">
                  <span class="service-chip"><i class="fa-solid fa-chart-pie text-[#39ff14]"></i> Google Analytics 4 (GA4)</span>
                  <span class="service-chip"><i class="fa-solid fa-chart-column text-blue-400"></i> Google Search Console</span>
                  <span class="service-chip"><i class="fa-solid fa-code text-purple-400"></i> Meta Pixel & Server CAPI</span>
                  <span class="service-chip"><i class="fa-solid fa-tag text-yellow-400"></i> Google Tag Manager (GTM)</span>
                  <span class="service-chip"><i class="fa-solid fa-desktop text-[#39ff14]"></i> Real-Time Live Dashboards</span>
                </div>
              </div>
              <div class="mt-6 pt-4 border-t border-white/10">
                <button class="open-consultation-modal text-[#39ff14] text-xs font-mono uppercase tracking-wider flex items-center gap-2 hover:underline" data-service="Analytics & Tracking">
                  <span>Fix Your Conversion Tracking</span>
                  <i class="fa-solid fa-arrow-right"></i>
                </button>
              </div>
            </div>

            <!-- Middle Visual Badge Box -->
            <div class="bg-[#13141f] rounded-xl p-6 border border-white/10 flex flex-col justify-center relative overflow-hidden">
              <div class="text-xs font-mono uppercase text-[#00f0ff] tracking-widest mb-4">Precision Attribution Stack</div>
              <div class="space-y-2 text-xs">
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex items-center justify-between">
                  <span>Server-Side CAPI:</span>
                  <span class="text-[#39ff14] font-bold">iOS 14+ Resilient</span>
                </div>
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex items-center justify-between">
                  <span>Event Match Quality (EMQ):</span>
                  <span class="text-[#00f0ff] font-bold">8.8 / 10 (Great)</span>
                </div>
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex items-center justify-between">
                  <span>Custom Looker Studio:</span>
                  <span class="text-[#cbfb45] font-bold">Live 24/7 Access</span>
                </div>
              </div>
            </div>

            <!-- Right: What runs inside it -->
            <div class="bg-black/40 rounded-xl p-5 border border-white/10">
              <div class="font-mono text-xs text-[#39ff14] uppercase tracking-widest pb-3 border-b border-white/10">
                What Runs Inside It
              </div>
              <ul class="mt-4 space-y-3 text-xs text-gray-300">
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">01</span>
                  <span><strong>Google Tag Manager Setup:</strong> Custom trigger tags for purchases, form submits, and phone clicks.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">02</span>
                  <span><strong>Meta CAPI Integration:</strong> Bypassing ad-blockers and iOS privacy restrictions with server events.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">03</span>
                  <span><strong>Cross-Domain Attribution:</strong> Unified tracking across ad clicks, landing pages, and final checkout.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">04</span>
                  <span><strong>Executive Reporting:</strong> Transparent weekly summaries showing real revenue, ROAS, and cost-per-lead.</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- 08: Influencer Marketing -->
        <div class="service-card-item service-split-box p-6 sm:p-8" data-category="content all">
          <div class="grid lg:grid-cols-[1.1fr_0.9fr_1fr] gap-8 items-stretch">
            <!-- Left Info -->
            <div class="flex flex-col justify-between">
              <div>
                <div class="flex items-center gap-3">
                  <span class="font-heading font-extrabold text-4xl text-[#39ff14]/30">08</span>
                  <span class="mono-tag mono-tag-green">CREATOR COLLABORATIONS</span>
                </div>
                <h3 class="font-heading font-bold text-2xl sm:text-3xl text-white mt-2">
                  Influencer & Creator Marketing
                </h3>
                <p class="text-gray-300 text-sm mt-3 leading-relaxed">
                  Leverage the trust of authentic content creators to blow up your brand awareness and generate instant sales spikes. We handle talent vetting, contract negotiation, creative briefs, and ROI tracking.
                </p>
                <!-- Sub service chips -->
                <div class="mt-5 flex flex-wrap gap-2">
                  <span class="service-chip"><i class="fa-solid fa-users-viewfinder text-[#39ff14]"></i> Influencer Selection & Vetting</span>
                  <span class="service-chip"><i class="fa-solid fa-handshake text-blue-400"></i> Brand Collaboration Deals</span>
                  <span class="service-chip"><i class="fa-solid fa-video text-pink-400"></i> Sponsored Content Management</span>
                  <span class="service-chip"><i class="fa-solid fa-receipt text-yellow-400"></i> Promo Code & Affiliate Tracking</span>
                  <span class="service-chip"><i class="fa-solid fa-bullhorn text-[#39ff14]"></i> Whitelisted Creator Ads</span>
                </div>
              </div>
              <div class="mt-6 pt-4 border-t border-white/10">
                <button class="open-consultation-modal text-[#39ff14] text-xs font-mono uppercase tracking-wider flex items-center gap-2 hover:underline" data-service="Influencer Marketing">
                  <span>Launch Creator Campaigns</span>
                  <i class="fa-solid fa-arrow-right"></i>
                </button>
              </div>
            </div>

            <!-- Middle Visual Badge Box -->
            <div class="bg-[#13141f] rounded-xl p-6 border border-white/10 flex flex-col justify-center relative overflow-hidden">
              <div class="text-xs font-mono uppercase text-[#39ff14] tracking-widest mb-4">Creator Network Reach</div>
              <div class="grid grid-cols-2 gap-3 text-center">
                <div class="bg-black/60 p-3 rounded-lg border border-white/5">
                  <div class="text-lg font-bold text-[#39ff14]">Nano & Micro</div>
                  <div class="text-[11px] text-gray-400">High Trust & Engagement</div>
                </div>
                <div class="bg-black/60 p-3 rounded-lg border border-white/5">
                  <div class="text-lg font-bold text-[#00f0ff]">Macro & Tier 1</div>
                  <div class="text-[11px] text-gray-400">Massive Scale</div>
                </div>
                <div class="bg-black/60 p-3 rounded-lg border border-white/5">
                  <div class="text-lg font-bold text-white">Whitelisted</div>
                  <div class="text-[11px] text-gray-400">Direct Ad Access</div>
                </div>
                <div class="bg-black/60 p-3 rounded-lg border border-white/5">
                  <div class="text-lg font-bold text-[#cbfb45]">Trackable</div>
                  <div class="text-[11px] text-gray-400">UTM & Coupon ROI</div>
                </div>
              </div>
            </div>

            <!-- Right: What runs inside it -->
            <div class="bg-black/40 rounded-xl p-5 border border-white/10">
              <div class="font-mono text-xs text-[#39ff14] uppercase tracking-widest pb-3 border-b border-white/10">
                What Runs Inside It
              </div>
              <ul class="mt-4 space-y-3 text-xs text-gray-300">
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">01</span>
                  <span><strong>Audience Legitimacy Vetting:</strong> Checking engagement rates, fake follower ratios, and past sponsor ROAS.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">02</span>
                  <span><strong>Creative Briefing:</strong> Providing clear guidelines without killing the creator's authentic voice.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">03</span>
                  <span><strong>Usage Rights & Whitelisting:</strong> Securing ad rights to run paid ads through the creator's handle.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">04</span>
                  <span><strong>Sales & Traffic Attribution:</strong> Direct tracking via custom links, discount codes, and pixel data.</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- 09: E-Commerce Marketing -->
        <div class="service-card-item service-split-box p-6 sm:p-8" data-category="ads funnels all">
          <div class="grid lg:grid-cols-[1.1fr_0.9fr_1fr] gap-8 items-stretch">
            <!-- Left Info -->
            <div class="flex flex-col justify-between">
              <div>
                <div class="flex items-center gap-3">
                  <span class="font-heading font-extrabold text-4xl text-[#39ff14]/30">09</span>
                  <span class="mono-tag mono-tag-green">STORE REVENUE HYPER-SCALE</span>
                </div>
                <h3 class="font-heading font-bold text-2xl sm:text-3xl text-white mt-2">
                  E-Commerce Growth & Scale
                </h3>
                <p class="text-gray-300 text-sm mt-3 leading-relaxed">
                  Turn your Shopify, WooCommerce, or custom online store into an automated cash-generating machine with Dynamic Product Ads (DPA), FB/IG catalog shops, abandoned cart recovery, and Google Merchant Center feeds.
                </p>
                <!-- Sub service chips -->
                <div class="mt-5 flex flex-wrap gap-2">
                  <span class="service-chip"><i class="fa-solid fa-store text-[#39ff14]"></i> Online Store Scaling</span>
                  <span class="service-chip"><i class="fa-solid fa-layer-group text-blue-400"></i> Dynamic Product Ads (DPA)</span>
                  <span class="service-chip"><i class="fa-brands fa-shopify text-green-400"></i> Shopify & WooCommerce Funnels</span>
                  <span class="service-chip"><i class="fa-brands fa-instagram text-pink-400"></i> Facebook & Instagram Shop</span>
                  <span class="service-chip"><i class="fa-solid fa-cart-arrow-down text-yellow-400"></i> Abandoned Cart Retargeting</span>
                  <span class="service-chip"><i class="fa-brands fa-google text-[#39ff14]"></i> Google Shopping Feeds</span>
                </div>
              </div>
              <div class="mt-6 pt-4 border-t border-white/10">
                <button class="open-consultation-modal text-[#39ff14] text-xs font-mono uppercase tracking-wider flex items-center gap-2 hover:underline" data-service="E-Commerce Marketing">
                  <span>Scale Your E-Commerce Store</span>
                  <i class="fa-solid fa-arrow-right"></i>
                </button>
              </div>
            </div>

            <!-- Middle Visual Badge Box -->
            <div class="bg-[#13141f] rounded-xl p-6 border border-white/10 flex flex-col justify-center relative overflow-hidden">
              <div class="text-xs font-mono uppercase text-[#39ff14] tracking-widest mb-4">E-Commerce Scale Engine</div>
              <div class="space-y-2 text-xs">
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex items-center justify-between">
                  <span>Cart Recovery Rate:</span>
                  <span class="text-[#39ff14] font-bold">28% Recovered</span>
                </div>
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex items-center justify-between">
                  <span>Average Order Value (AOV):</span>
                  <span class="text-[#00f0ff] font-bold">+35% via Bundles</span>
                </div>
                <div class="bg-black/60 p-2.5 rounded-lg border border-white/5 flex items-center justify-between">
                  <span>Catalog Feed Sync:</span>
                  <span class="text-[#cbfb45] font-bold">Real-Time Inventory</span>
                </div>
              </div>
            </div>

            <!-- Right: What runs inside it -->
            <div class="bg-black/40 rounded-xl p-5 border border-white/10">
              <div class="font-mono text-xs text-[#39ff14] uppercase tracking-widest pb-3 border-b border-white/10">
                What Runs Inside It
              </div>
              <ul class="mt-4 space-y-3 text-xs text-gray-300">
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">01</span>
                  <span><strong>Catalog & Feed Optimization:</strong> Connecting dynamic XML feeds with high-resolution image sets.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">02</span>
                  <span><strong>Omnichannel Dynamic Retargeting:</strong> Showing customers the exact item they viewed or added to cart.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">03</span>
                  <span><strong>Checkout CRO & Upsells:</strong> 1-click upsells, quantity discounts, and trust badge integration.</span>
                </li>
                <li class="flex items-start gap-2.5">
                  <span class="font-mono text-[#39ff14] font-bold">04</span>
                  <span><strong>Customer Lifetime Value (LTV):</strong> Post-purchase SMS & email flows to generate repeat orders.</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

      </div>

    </div>
  </section>

  <!-- Interactive ROI & Ad Spend Calculator -->
  <section id="calculator" class="py-24 bg-[#060608] relative border-b border-white/10 cyber-grid">
    <div class="max-w-6xl mx-auto px-5 sm:px-8">
      
      <div class="text-center max-w-3xl mx-auto">
        <div class="mono-tag mono-tag-cyan">
          <i class="fa-solid fa-calculator"></i> INTERACTIVE GROWTH SIMULATOR
        </div>
        <h2 class="font-heading font-extrabold text-3xl sm:text-5xl mt-4 text-white tracking-tight">
          Calculate Your Projected <span class="text-[#39ff14]">Revenue & Leads</span>
        </h2>
        <p class="text-gray-400 mt-3 text-base">
          Adjust the sliders below to see what your monthly marketing budget can produce with the NEX-47 Growth Catalyst engine.
        </p>
      </div>

      <div class="mt-14 glass-card p-8 sm:p-12 border border-[#39ff14]/30 shadow-[0_0_40px_rgba(57,255,20,0.1)]">
        <div class="grid lg:grid-cols-12 gap-10 items-center">
          
          <!-- Left Controls -->
          <div class="lg:col-span-7 space-y-8">
            
            <!-- Ad Spend Slider -->
            <div>
              <div class="flex justify-between items-center mb-3">
                <label class="text-sm font-semibold text-white uppercase tracking-wider font-mono">
                  Monthly Ad Spend Budget:
                </label>
                <span id="spendDisplay" class="font-heading font-extrabold text-2xl text-[#39ff14]">$500</span>
              </div>
              <input type="range" id="adSpendInput" min="100" max="3000" step="50" value="500" class="w-full" />
              <div class="flex justify-between text-[11px] font-mono text-gray-500 mt-1">
                <span>$500 (Starter)</span>
                <span>$10,000 (Growth)</span>
                <span>$25,000+ (Scale)</span>
              </div>
            </div>

            <!-- Target ROAS Multiplier -->
            <div>
              <div class="flex justify-between items-center mb-3">
                <label class="text-sm font-semibold text-white uppercase tracking-wider font-mono">
                  Target ROAS Multiplier:
                </label>
                <span id="roasDisplay" class="font-heading font-extrabold text-2xl text-[#00f0ff]">4.5x</span>
              </div>
              <input type="range" id="roasMultiplier" min="2.0" max="10.0" step="0.5" value="4.5" class="w-full" />
              <div class="flex justify-between text-[11px] font-mono text-gray-500 mt-1">
                <span>2.0x (Conservative)</span>
                <span>5.0x (Standard Target)</span>
                <span>10.0x (High-Ticket / Viral)</span>
              </div>
            </div>

            <!-- Industry Selector -->
            <div>
              <label class="block text-sm font-semibold text-white uppercase tracking-wider font-mono mb-2">
                Select Your Industry / Niche:
              </label>
              <select id="industrySelect" class="w-full bg-[#13141f] border border-white/20 rounded-xl px-4 py-3.5 text-white focus:border-[#39ff14] outline-none text-sm font-medium">
                <option value="ecommerce">E-Commerce & Retail (Fashion, Electronics, Beauty)</option>
                <option value="b2b">B2B Services & High-Ticket Consulting</option>
                <option value="realestate">Real Estate & Property Development</option>
                <option value="saas">SaaS & Technology Startups</option>
              </select>
            </div>

          </div>

          <!-- Right Calculation Results Card -->
          <div class="lg:col-span-5 bg-[#0a0b10] border-2 border-[#39ff14]/50 rounded-2xl p-6 sm:p-8 relative overflow-hidden shadow-[0_0_30px_rgba(57,255,20,0.2)]">
            <div class="absolute top-0 right-0 w-36 h-36 bg-[#39ff14]/15 rounded-full blur-3xl"></div>
            
            <div class="font-mono text-xs uppercase text-[#39ff14] tracking-widest flex items-center gap-2">
              <span class="pulse-dot"></span>
              <span>Projected Output Breakdown</span>
            </div>

            <div class="mt-6 space-y-6">
              <div>
                <div class="text-xs uppercase font-mono text-gray-400">Estimated Projected Revenue:</div>
                <div id="calcRevenue" class="font-heading font-extrabold text-4xl sm:text-5xl text-[#39ff14] glow-neon-text mt-1">
                  $11,250
                </div>
              </div>

              <div class="grid grid-cols-2 gap-4 pt-4 border-t border-white/10">
                <div>
                  <div class="text-[11px] uppercase font-mono text-gray-400">Estimated Leads / Orders:</div>
                  <div id="calcLeads" class="font-heading font-bold text-2xl text-white mt-1">250+</div>
                </div>
                <div>
                  <div class="text-[11px] uppercase font-mono text-gray-400">Net Return ROI:</div>
                  <div id="calcROI" class="font-heading font-bold text-2xl text-[#00f0ff] mt-1">+350%</div>
                </div>
              </div>

              <div class="pt-4 border-t border-white/10">
                <div class="text-[11px] uppercase font-mono text-gray-400">Estimated Audience Reach:</div>
                <div id="calcReach" class="font-heading font-bold text-xl text-[#cbfb45] mt-1">416.7k+</div>
              </div>

              <button class="open-consultation-modal btn-neon w-full justify-center text-xs uppercase tracking-wider mt-4">
                <span>Claim This Growth Plan</span>
                <i class="fa-solid fa-arrow-right"></i>
              </button>
            </div>
          </div>

        </div>
      </div>

    </div>
  </section>

  <!-- 4-Step Catalyst Growth Process -->
  <section id="process" class="py-24 bg-[#08090e] relative border-b border-white/10">
    <div class="max-w-7xl mx-auto px-5 sm:px-8">
      
      <div class="text-center max-w-3xl mx-auto">
        <div class="mono-tag mono-tag-green">
          <i class="fa-solid fa-diagram-project"></i> THE NEX-47 METHODOLOGY
        </div>
        <h2 class="font-heading font-extrabold text-3xl sm:text-5xl mt-4 text-white tracking-tight">
          How We Scale Brands in <span class="text-[#39ff14]">4 Precision Steps</span>
        </h2>
        <p class="text-gray-400 mt-3 text-base">
          No guesswork. No vanity metrics. A systematic, repeatable framework designed to maximize your bottom-line profit.
        </p>
      </div>

      <div class="mt-16 grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
        
        <!-- Step 1 -->
        <div class="glass-card p-6 border-t-2 border-t-[#39ff14] relative">
          <div class="font-mono text-xs text-[#39ff14] uppercase tracking-widest">Phase 01</div>
          <div class="font-heading font-bold text-xl text-white mt-2">Deep Audit & Blueprint</div>
          <p class="text-xs text-gray-400 mt-3 leading-relaxed">
            We analyze your competitors, past ad data, landing pages, and audience demographics to map out an unbeatable growth roadmap.
          </p>
        </div>

        <!-- Step 2 -->
        <div class="glass-card p-6 border-t-2 border-t-[#00f0ff] relative">
          <div class="font-mono text-xs text-[#00f0ff] uppercase tracking-widest">Phase 02</div>
          <div class="font-heading font-bold text-xl text-white mt-2">Creative & Funnel Build</div>
          <p class="text-xs text-gray-400 mt-3 leading-relaxed">
            We script viral video hooks, design high-converting visual ads, set up Meta CAPI tracking, and craft friction-free landing pages.
          </p>
        </div>

        <!-- Step 3 -->
        <div class="glass-card p-6 border-t-2 border-t-[#cbfb45] relative">
          <div class="font-mono text-xs text-[#cbfb45] uppercase tracking-widest">Phase 03</div>
          <div class="font-heading font-bold text-xl text-white mt-2">Omnichannel Launch</div>
          <p class="text-xs text-gray-400 mt-3 leading-relaxed">
            We deploy multi-tiered campaigns across Meta, Google, and TikTok simultaneously, capturing both low-hanging and high-intent buyers.
          </p>
        </div>

        <!-- Step 4 -->
        <div class="glass-card p-6 border-t-2 border-t-[#39ff14] relative">
          <div class="font-mono text-xs text-[#39ff14] uppercase tracking-widest">Phase 04</div>
          <div class="font-heading font-bold text-xl text-white mt-2">Hyper-Optimization & Scale</div>
          <p class="text-xs text-gray-400 mt-3 leading-relaxed">
            We cut losing creatives, double down on high-ROAS winners, automate WhatsApp follow-ups, and aggressively scale ad spend profitably.
          </p>
        </div>

      </div>

    </div>
  </section>

  <!-- Case Studies & Proven Results -->
  <section id="results" class="py-24 bg-[#060608] relative border-b border-white/10 cyber-grid">
    <div class="max-w-7xl mx-auto px-5 sm:px-8">
      
      <div class="text-center max-w-3xl mx-auto">
        <div class="mono-tag mono-tag-green">
          <i class="fa-solid fa-trophy"></i> VERIFIED TRACK RECORD
        </div>
        <h2 class="font-heading font-extrabold text-3xl sm:text-5xl mt-4 text-white tracking-tight">
          Real Numbers. <span class="text-[#39ff14]">Real Scaled Brands.</span>
        </h2>
        <p class="text-gray-400 mt-3 text-base">
          Here is how our campaigns have delivered record-breaking revenue and qualified leads for our partners.
        </p>
      </div>

      <div class="mt-16 grid md:grid-cols-3 gap-8">
        
        <!-- Case Study 1 -->
        <div class="glass-card p-7 border border-white/10 hover:border-[#39ff14]/50 flex flex-col justify-between">
          <div>
            <div class="flex justify-between items-center">
              <span class="mono-tag mono-tag-green">E-COMMERCE FASHION</span>
              <span class="text-xs font-mono text-gray-400">90-Day Scale</span>
            </div>
            <h3 class="font-heading font-bold text-xl text-white mt-4">
              Scaled From $12k/Mo to $85k/Mo at 5.2x ROAS
            </h3>
            <p class="text-xs text-gray-400 mt-3 leading-relaxed">
              Implemented dynamic catalog ads (DPA), TikTok viral UGC creatives, and 1-click checkout recovery, turning paid ads into their #1 sales driver.
            </p>
          </div>
          <div class="mt-6 pt-4 border-t border-white/10 grid grid-cols-2 gap-2 text-center">
            <div class="bg-black/40 p-2.5 rounded-lg">
              <div class="font-bold text-[#39ff14] text-lg">5.2x ROAS</div>
              <div class="text-[10px] text-gray-500 uppercase">Return On Spend</div>
            </div>
            <div class="bg-black/40 p-2.5 rounded-lg">
              <div class="font-bold text-white text-lg">$85,000+</div>
              <div class="text-[10px] text-gray-500 uppercase">Monthly Revenue</div>
            </div>
          </div>
        </div>

        <!-- Case Study 2 -->
        <div class="glass-card p-7 border border-white/10 hover:border-[#00f0ff]/50 flex flex-col justify-between">
          <div>
            <div class="flex justify-between items-center">
              <span class="mono-tag mono-tag-cyan">B2B SAAS / TECH</span>
              <span class="text-xs font-mono text-gray-400">60-Day Campaign</span>
            </div>
            <h3 class="font-heading font-bold text-xl text-white mt-4">
              1,450 Qualified Demo Leads at -48% Lower CPL
            </h3>
            <p class="text-xs text-gray-400 mt-3 leading-relaxed">
              Restructured Google Search with exact-match buyer keywords, built interactive product tour landing pages, and automated CRM routing.
            </p>
          </div>
          <div class="mt-6 pt-4 border-t border-white/10 grid grid-cols-2 gap-2 text-center">
            <div class="bg-black/40 p-2.5 rounded-lg">
              <div class="font-bold text-[#00f0ff] text-lg">1,450+</div>
              <div class="text-[10px] text-gray-500 uppercase">B2B Demos Booked</div>
            </div>
            <div class="bg-black/40 p-2.5 rounded-lg">
              <div class="font-bold text-white text-lg">-48% CPL</div>
              <div class="text-[10px] text-gray-500 uppercase">Acquisition Cost</div>
            </div>
          </div>
        </div>

        <!-- Case Study 3 -->
        <div class="glass-card p-7 border border-white/10 hover:border-[#cbfb45]/50 flex flex-col justify-between">
          <div>
            <div class="flex justify-between items-center">
              <span class="mono-tag mono-tag-green">REAL ESTATE & LUXURY</span>
              <span class="text-xs font-mono text-gray-400">Direct Funnel</span>
            </div>
            <h3 class="font-heading font-bold text-xl text-white mt-4">
              $4.8M Property Inventory Sold via WhatsApp Leads
            </h3>
            <p class="text-xs text-gray-400 mt-3 leading-relaxed">
              Targeted high-net-worth investors across GCC and Pakistan using video walkthrough ads hooked directly into automated WhatsApp consultation chats.
            </p>
          </div>
          <div class="mt-6 pt-4 border-t border-white/10 grid grid-cols-2 gap-2 text-center">
            <div class="bg-black/40 p-2.5 rounded-lg">
              <div class="font-bold text-[#cbfb45] text-lg">$4.8M+</div>
              <div class="text-[10px] text-gray-500 uppercase">Gross Sales Value</div>
            </div>
            <div class="bg-black/40 p-2.5 rounded-lg">
              <div class="font-bold text-white text-lg">38 Deals</div>
              <div class="text-[10px] text-gray-500 uppercase">Units Closed</div>
            </div>
          </div>
        </div>

      </div>

    </div>
  </section>

  <!-- FAQ Accordion Section -->
  <section id="faq" class="py-24 bg-[#08090e] relative border-b border-white/10">
    <div class="max-w-4xl mx-auto px-5 sm:px-8">
      
      <div class="text-center">
        <div class="mono-tag mono-tag-green">
          <i class="fa-solid fa-circle-question"></i> COMMON INQUIRIES
        </div>
        <h2 class="font-heading font-extrabold text-3xl sm:text-5xl mt-4 text-white tracking-tight">
          Frequently Asked <span class="text-[#39ff14]">Questions</span>
        </h2>
      </div>

      <div class="mt-14 space-y-4">
        
        <!-- FAQ 1 -->
        <div class="faq-item glass-card border border-white/10 overflow-hidden">
          <button class="faq-trigger w-full px-6 py-5 text-left flex justify-between items-center gap-4 hover:text-[#39ff14] transition-colors">
            <span class="font-medium text-base sm:text-lg text-white">How quickly can I expect to see results from campaigns?</span>
            <i class="faq-icon fa-solid fa-chevron-down text-gray-400 text-sm transition-transform duration-300"></i>
          </button>
          <div class="faq-content hidden px-6 pb-6 text-sm text-gray-400 leading-relaxed border-t border-white/5 pt-3">
            For paid channels (Meta Ads, Google PPC, and Lead Generation Funnels), we typically start driving qualified leads and direct purchases within 48 to 72 hours of launch. For organic SEO and Content Marketing, results compound over 60 to 90 days into sustainable long-term authority.
          </div>
        </div>

        <!-- FAQ 2 -->
        <div class="faq-item glass-card border border-white/10 overflow-hidden">
          <button class="faq-trigger w-full px-6 py-5 text-left flex justify-between items-center gap-4 hover:text-[#39ff14] transition-colors">
            <span class="font-medium text-base sm:text-lg text-white">Do you handle creative production (video ads, reels, copywriting)?</span>
            <i class="faq-icon fa-solid fa-chevron-down text-gray-400 text-sm transition-transform duration-300"></i>
          </button>
          <div class="faq-content hidden px-6 pb-6 text-sm text-gray-400 leading-relaxed border-t border-white/5 pt-3">
            Yes, 100%! We have an in-house team of video editors, motion graphic artists, copywriters, and creative strategists who produce high-retention Reels, TikToks, and static ad creatives tailored for your specific audience.
          </div>
        </div>

        <!-- FAQ 3 -->
        <div class="faq-item glass-card border border-white/10 overflow-hidden">
          <button class="faq-trigger w-full px-6 py-5 text-left flex justify-between items-center gap-4 hover:text-[#39ff14] transition-colors">
            <span class="font-medium text-base sm:text-lg text-white">How is conversion tracking and reporting handled?</span>
            <i class="faq-icon fa-solid fa-chevron-down text-gray-400 text-sm transition-transform duration-300"></i>
          </button>
          <div class="faq-content hidden px-6 pb-6 text-sm text-gray-400 leading-relaxed border-t border-white/5 pt-3">
            We configure server-side tracking (Meta CAPI), Google Tag Manager, and GA4 to ensure zero data loss from ad blockers or iOS privacy updates. You get access to a live 24/7 client dashboard plus weekly performance calls.
          </div>
        </div>

        <!-- FAQ 4 -->
        <div class="faq-item glass-card border border-white/10 overflow-hidden">
          <button class="faq-trigger w-full px-6 py-5 text-left flex justify-between items-center gap-4 hover:text-[#39ff14] transition-colors">
            <span class="font-medium text-base sm:text-lg text-white">What budget do I need to get started with NEX-47?</span>
            <i class="faq-icon fa-solid fa-chevron-down text-gray-400 text-sm transition-transform duration-300"></i>
          </button>
          <div class="faq-content hidden px-6 pb-6 text-sm text-gray-400 leading-relaxed border-t border-white/5 pt-3">
            We work with high-potential brands starting from $1,000/month in ad spend up to enterprise budgets of $50,000+/month. We will tailor the exact mix of services based on your current unit economics and profit margins.
          </div>
        </div>

      </div>

    </div>
  </section>

  <!-- Contact & Free Audit Section -->
  <section id="contact" class="py-24 bg-[#060608] relative cyber-grid">
    <div class="max-w-6xl mx-auto px-5 sm:px-8">
      <div class="grid lg:grid-cols-12 gap-12 items-center">
        
        <!-- Left Info -->
        <div class="lg:col-span-5 space-y-6">
          <div class="mono-tag mono-tag-green">
            <i class="fa-solid fa-paper-plane"></i> READY FOR HYPER-GROWTH?
          </div>
          <h2 class="font-heading font-extrabold text-3xl sm:text-4xl text-white">
            Let's Build Your <span class="text-[#39ff14]">Dominant Funnel</span>
          </h2>
          <p class="text-sm text-gray-300 leading-relaxed">
            Fill out the brief or connect directly on WhatsApp. We will audit your current presence, uncover hidden leaks in your funnel, and send a customized growth plan.
          </p>

          <div class="space-y-4 pt-4">
            <div class="flex items-center gap-3 text-sm text-gray-300">
              <div class="w-10 h-10 rounded-full bg-[#11121a] border border-[#39ff14]/30 flex items-center justify-center text-[#39ff14]">
                <i class="fa-brands fa-whatsapp text-lg"></i>
              </div>
              <div>
                <div class="text-[11px] font-mono uppercase text-gray-400">Direct WhatsApp</div>
                <a href="https://wa.me/923495438083" target="_blank" class="text-white hover:text-[#39ff14] font-semibold">+92 349 5438083</a>
              </div>
            </div>

            <div class="flex items-center gap-3 text-sm text-gray-300">
              <div class="w-10 h-10 rounded-full bg-[#11121a] border border-[#00f0ff]/30 flex items-center justify-center text-[#00f0ff]">
                <i class="fa-solid fa-envelope text-lg"></i>
              </div>
              <div>
                <div class="text-[11px] font-mono uppercase text-gray-400">Official Inquiries</div>
                <a href="mailto:growth@nex47.com" class="text-white hover:text-[#00f0ff] font-semibold">growth@nex47.com</a>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Form -->
        <div class="lg:col-span-7 glass-card p-8 sm:p-10 border border-[#39ff14]/30 shadow-[0_0_40px_rgba(57,255,20,0.15)]">
          <form id="mainContactForm" class="space-y-4">
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-mono uppercase text-gray-400 mb-1.5">Your Full Name *</label>
                <input type="text" name="client_name" required placeholder="e.g. John Doe / Ahmed Khan" class="w-full bg-[#13141f] border border-white/15 rounded-xl px-4 py-3 text-sm text-white focus:border-[#39ff14] outline-none" />
              </div>
              <div>
                <label class="block text-xs font-mono uppercase text-gray-400 mb-1.5">WhatsApp / Phone *</label>
                <input type="tel" name="client_phone" required placeholder="e.g. +92 300 1234567" class="w-full bg-[#13141f] border border-white/15 rounded-xl px-4 py-3 text-sm text-white focus:border-[#39ff14] outline-none" />
              </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-mono uppercase text-gray-400 mb-1.5">Brand or Website URL</label>
                <input type="text" name="brand_name" placeholder="e.g. mybrand.com" class="w-full bg-[#13141f] border border-white/15 rounded-xl px-4 py-3 text-sm text-white focus:border-[#39ff14] outline-none" />
              </div>
              <div>
                <label class="block text-xs font-mono uppercase text-gray-400 mb-1.5">Primary Service Needed *</label>
                <select name="service_type" class="w-full bg-[#13141f] border border-white/15 rounded-xl px-4 py-3 text-sm text-white focus:border-[#39ff14] outline-none">
                  <option value="All-in-One Growth Plan">All-in-One Growth Plan (Recommended)</option>
                  <option value="Social Media Marketing (SMM)">Social Media Marketing (SMM)</option>
                  <option value="Meta Ads / Facebook Ads">Meta Ads / Facebook Ads</option>
                  <option value="Google Ads (PPC)">Google Ads (PPC)</option>
                  <option value="SEO (Search Engine Optimization)">SEO (Search Engine Optimization)</option>
                  <option value="Content Marketing">Content Marketing & Reels</option>
                  <option value="Lead Generation & Funnels">Lead Generation & Funnels</option>
                  <option value="Analytics & Tracking">Analytics & Conversion Tracking</option>
                  <option value="Influencer Marketing">Influencer Marketing</option>
                  <option value="E-Commerce Marketing">E-Commerce Marketing & Scale</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-gray-400 mb-1.5">Estimated Monthly Budget</label>
              <select name="budget" class="w-full bg-[#13141f] border border-white/15 rounded-xl px-4 py-3 text-sm text-white focus:border-[#39ff14] outline-none">
                <option value="$100 - $300">$100 - $300 / Month (Starter / Testing)</option>
                <option value="$300 - $500">$300 - $500 / Month (Growth)</option>
                <option value="$500 - $1,000">$500 - $1,000 / Month (Scale)</option>
                <option value="$1,000 - $1,500">$1,000 - $1,500 / Month (Hyper Scale)</option>
                <option value="$1,500+">$1,500+ / Month (Enterprise)</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-mono uppercase text-gray-400 mb-1.5">Your Core Growth Goal</label>
              <textarea name="message" rows="3" placeholder="Tell us about your target revenue, current bottlenecks, or goals..." class="w-full bg-[#13141f] border border-white/15 rounded-xl px-4 py-3 text-sm text-white focus:border-[#39ff14] outline-none"></textarea>
            </div>

            <button type="submit" class="btn-neon w-full justify-center py-4 text-xs uppercase tracking-wider font-bold">
              <span>Send Growth Request via WhatsApp</span>
              <i class="fa-brands fa-whatsapp text-base"></i>
            </button>
          </form>
        </div>

      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-[#040406] border-t border-white/10 pt-16 pb-12 text-xs text-gray-400">
    <div class="max-w-7xl mx-auto px-5 sm:px-8">
      <div class="grid grid-cols-2 md:grid-cols-5 gap-10">
        
        <!-- Col 1: Brand Info -->
        <div class="col-span-2 space-y-4">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-black border border-[#39ff14]/40 p-1 flex items-center justify-center">
              <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;filter:drop-shadow(0 0 10px #39ff14)"><polygon points="30,20 80,20 170,180 120,180" fill="#39ff14"/><polygon points="170,20 120,20 30,180 80,180" fill="#39ff14"/></svg>
            </div>
            <div>
              <div class="font-heading font-extrabold text-lg text-white">NEX - 47</div>
              <div class="font-mono text-[9px] uppercase tracking-widest text-[#39ff14]">CATALYS'S</div>
            </div>
          </div>
          <p class="text-xs text-gray-400 max-w-sm leading-relaxed">
            NEX - 47 CATALYS'S is a full-service performance marketing and brand acceleration agency helping forward-thinking businesses scale through data, creative excellence, and full-funnel dominance.
          </p>
          <div class="flex gap-3 text-base text-gray-400">
            <a href="#" class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center hover:text-[#39ff14] hover:bg-white/10 transition-colors"><i class="fa-brands fa-facebook"></i></a>
            <a href="#" class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center hover:text-[#39ff14] hover:bg-white/10 transition-colors"><i class="fa-brands fa-instagram"></i></a>
            <a href="#" class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center hover:text-[#39ff14] hover:bg-white/10 transition-colors"><i class="fa-brands fa-linkedin"></i></a>
            <a href="#" class="w-8 h-8 rounded-full bg-white/5 flex items-center justify-center hover:text-[#39ff14] hover:bg-white/10 transition-colors"><i class="fa-brands fa-tiktok"></i></a>
          </div>
        </div>

        <!-- Col 2: Services 1-5 -->
        <div>
          <div class="font-mono uppercase text-[#39ff14] tracking-wider mb-4 font-semibold">Services (01-05)</div>
          <ul class="space-y-2 text-gray-400">
            <li><a href="#services" class="hover:text-white transition-colors">01. Social Media Marketing</a></li>
            <li><a href="#services" class="hover:text-white transition-colors">02. Meta & Facebook Ads</a></li>
            <li><a href="#services" class="hover:text-white transition-colors">03. Google PPC & Search</a></li>
            <li><a href="#services" class="hover:text-white transition-colors">04. SEO & Rankings</a></li>
            <li><a href="#services" class="hover:text-white transition-colors">05. Content Marketing</a></li>
          </ul>
        </div>

        <!-- Col 3: Services 6-9 -->
        <div>
          <div class="font-mono uppercase text-[#39ff14] tracking-wider mb-4 font-semibold">Services (06-09)</div>
          <ul class="space-y-2 text-gray-400">
            <li><a href="#services" class="hover:text-white transition-colors">06. Lead Gen & Funnels</a></li>
            <li><a href="#services" class="hover:text-white transition-colors">07. Analytics & GA4</a></li>
            <li><a href="#services" class="hover:text-white transition-colors">08. Influencer Marketing</a></li>
            <li><a href="#services" class="hover:text-white transition-colors">09. E-Commerce Scale</a></li>
          </ul>
        </div>

        <!-- Col 4: Quick Links -->
        <div>
          <div class="font-mono uppercase text-[#00f0ff] tracking-wider mb-4 font-semibold">Company</div>
          <ul class="space-y-2 text-gray-400">
            <li><a href="#hero" class="hover:text-white transition-colors">Home Portal</a></li>
            <li><a href="#calculator" class="hover:text-white transition-colors">ROI Calculator</a></li>
            <li><a href="#process" class="hover:text-white transition-colors">Our Process</a></li>
            <li><a href="#results" class="hover:text-white transition-colors">Case Studies</a></li>
            <li><a href="#faq" class="hover:text-white transition-colors">FAQ</a></li>
          </ul>
        </div>

      </div>

      <div class="mt-12 pt-8 border-t border-white/5 flex flex-col sm:flex-row justify-between items-center gap-4 text-center sm:text-left">
        <div>&copy; 2026 NEX - 47 CATALYS'S. All Rights Reserved. Built for high-performance marketing.</div>
        <div class="flex gap-4">
          <a href="#" class="hover:text-[#39ff14]">Privacy Policy</a>
          <span>·</span>
          <a href="#" class="hover:text-[#39ff14]">Terms of Service</a>
        </div>
      </div>
    </div>
  </footer>

  <!-- Floating WhatsApp Quick Button -->
  <a href="https://wa.me/923495438083?text=Hi%20NEX-47%20Catalysts,%20I%20am%20interested%20in%20scaling%20my%20business." target="_blank" class="whatsapp-float" title="Chat on WhatsApp">
    <i class="fa-brands fa-whatsapp text-3xl"></i>
  </a>

  <!-- Consultation / Free Audit Booking Modal -->
  <div id="auditModal" class="fixed inset-0 z-50 modal-overlay hidden items-center justify-center p-4">
    <div class="bg-[#0c0d14] border-2 border-[#39ff14]/50 rounded-2xl max-w-lg w-full p-6 sm:p-8 relative shadow-[0_0_50px_rgba(57,255,20,0.3)] max-h-[90vh] overflow-y-auto">
      <button id="closeModalBtn" class="absolute top-4 right-4 text-gray-400 hover:text-white">
        <i class="fa-solid fa-xmark text-xl"></i>
      </button>

      <div class="flex items-center gap-2 text-xs font-mono text-[#39ff14] uppercase tracking-widest">
        <span class="pulse-dot"></span>
        <span>GROWTH BLUEPRINT RESERVATION</span>
      </div>

      <h3 class="font-heading font-extrabold text-2xl text-white mt-2">
        Get Your Custom <span class="text-[#39ff14]">NEX-47 Plan</span>
      </h3>
      <p class="text-xs text-gray-400 mt-1">
        Fill out your details to receive an instant performance review and strategy roadmap.
      </p>

      <form id="modalAuditForm" class="mt-6 space-y-4">
        <input type="hidden" id="modalServiceInput" name="service_type" value="All-in-One Growth Plan" />
        
        <div>
          <label class="block text-xs font-mono uppercase text-gray-400 mb-1">Your Name *</label>
          <input type="text" name="client_name" required placeholder="John Doe" class="w-full bg-[#13141f] border border-white/20 rounded-xl px-4 py-2.5 text-sm text-white focus:border-[#39ff14] outline-none" />
        </div>

        <div>
          <label class="block text-xs font-mono uppercase text-gray-400 mb-1">WhatsApp / Phone Number *</label>
          <input type="tel" name="client_phone" required placeholder="+92 300 1234567" class="w-full bg-[#13141f] border border-white/20 rounded-xl px-4 py-2.5 text-sm text-white focus:border-[#39ff14] outline-none" />
        </div>

        <div>
          <label class="block text-xs font-mono uppercase text-gray-400 mb-1">Brand Name or Website</label>
          <input type="text" name="brand_name" placeholder="mybrand.com" class="w-full bg-[#13141f] border border-white/20 rounded-xl px-4 py-2.5 text-sm text-white focus:border-[#39ff14] outline-none" />
        </div>

        <div>
          <label class="block text-xs font-mono uppercase text-gray-400 mb-1">Monthly Ad Budget Tier</label>
          <select name="budget" class="w-full bg-[#13141f] border border-white/20 rounded-xl px-4 py-2.5 text-sm text-white focus:border-[#39ff14] outline-none">
            <option value="$100 - $300">$100 - $300 (Starter / Testing)</option>
            <option value="$300 - $500">$300 - $500 (Growth)</option>
            <option value="$500 - $1,000">$500 - $1,000 (Scale)</option>
            <option value="$1,000 - $1,500">$1,000 - $1,500 (Hyper Scale)</option>
            <option value="$1,500+">$1,500+ (Enterprise)</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-mono uppercase text-gray-400 mb-1">Specific Goal / Requirement</label>
          <textarea name="message" rows="2" placeholder="e.g. Need to scale Meta & Google ads ROAS..." class="w-full bg-[#13141f] border border-white/20 rounded-xl px-4 py-2.5 text-sm text-white focus:border-[#39ff14] outline-none"></textarea>
        </div>

        <button type="submit" class="btn-neon w-full justify-center py-3.5 text-xs uppercase tracking-wider font-bold">
          <span>Forward Brief to WhatsApp</span>
          <i class="fa-brands fa-whatsapp text-base"></i>
        </button>
      </form>
    </div>
  </div>

  <!-- Custom App Script -->
    <!-- Inline Engine & Interactive Logic -->
  <script>
/**
 * NEX - 47 CATALYS'S - Core Interactive Engine
 * Handles ROI Calculator, Service Filters, Database AJAX Lead Storage & WhatsApp Routing
 */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Mobile Menu Toggle
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const mobileDrawer = document.getElementById('mobileDrawer');
  const closeMobileMenu = document.getElementById('closeMobileMenu');
  const mobileNavLinks = document.querySelectorAll('.mobile-nav-link');

  if (mobileMenuBtn && mobileDrawer) {
    mobileMenuBtn.addEventListener('click', () => {
      mobileDrawer.classList.remove('translate-x-full');
      document.body.classList.add('overflow-hidden');
    });

    const closeDrawer = () => {
      mobileDrawer.classList.add('translate-x-full');
      document.body.classList.remove('overflow-hidden');
    };

    if (closeMobileMenu) {
      closeMobileMenu.addEventListener('click', closeDrawer);
    }

    mobileNavLinks.forEach(link => {
      link.addEventListener('click', closeDrawer);
    });
  }

  // 2. Interactive ROI & Ad Spend Calculator
  const adSpendInput = document.getElementById('adSpendInput');
  const spendDisplay = document.getElementById('spendDisplay');
  const industrySelect = document.getElementById('industrySelect');
  const roasMultiplier = document.getElementById('roasMultiplier');
  const roasDisplay = document.getElementById('roasDisplay');

  const calcLeads = document.getElementById('calcLeads');
  const calcRevenue = document.getElementById('calcRevenue');
  const calcROI = document.getElementById('calcROI');
  const calcReach = document.getElementById('calcReach');

  const calculateGrowth = () => {
    if (!adSpendInput || !calcRevenue) return;

    const spend = parseFloat(adSpendInput.value) || 500;
    const roas = parseFloat(roasMultiplier ? roasMultiplier.value : 3.5) || 3.5;
    const industry = industrySelect ? industrySelect.value : 'ecommerce';

    // Update display values
    if (spendDisplay) spendDisplay.textContent = `$${spend.toLocaleString()}`;
    if (roasDisplay) roasDisplay.textContent = `${roas.toFixed(1)}x`;

    // Calculation multipliers based on industry
    let leadCostMultiplier = 15; // default $15/lead
    let cpm = 8; // $8 per 1000 impressions

    if (industry === 'ecommerce') {
      leadCostMultiplier = 10;
      cpm = 6;
    } else if (industry === 'b2b') {
      leadCostMultiplier = 35;
      cpm = 14;
    } else if (industry === 'realestate') {
      leadCostMultiplier = 25;
      cpm = 10;
    } else if (industry === 'saas') {
      leadCostMultiplier = 20;
      cpm = 12;
    }

    const projectedRevenue = Math.round(spend * roas);
    const estimatedLeads = Math.round(spend / leadCostMultiplier);
    const estimatedReach = Math.round((spend / cpm) * 1000);
    const netProfitMultiplier = Math.round(((projectedRevenue - spend) / spend) * 100);

    // Render results
    if (calcRevenue) calcRevenue.textContent = `$${projectedRevenue.toLocaleString()}`;
    if (calcLeads) calcLeads.textContent = `${estimatedLeads.toLocaleString()}+`;
    if (calcROI) calcROI.textContent = `+${netProfitMultiplier}%`;
    if (calcReach) calcReach.textContent = `${(estimatedReach / 1000).toFixed(1)}k+`;
  };

  if (adSpendInput) adSpendInput.addEventListener('input', calculateGrowth);
  if (roasMultiplier) roasMultiplier.addEventListener('input', calculateGrowth);
  if (industrySelect) industrySelect.addEventListener('change', calculateGrowth);

  calculateGrowth();

  // 3. Consultation / Growth Plan Modal
  const openModalBtns = document.querySelectorAll('.open-consultation-modal');
  const auditModal = document.getElementById('auditModal');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const modalServiceInput = document.getElementById('modalServiceInput');

  openModalBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const service = btn.getAttribute('data-service') || 'All-in-One Growth Plan';
      if (modalServiceInput) modalServiceInput.value = service;
      if (auditModal) {
        auditModal.classList.remove('hidden');
        auditModal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
      }
    });
  });

  const closeAuditModal = () => {
    if (auditModal) {
      auditModal.classList.add('hidden');
      auditModal.classList.remove('flex');
      document.body.classList.remove('overflow-hidden');
    }
  };

  if (closeModalBtn) closeModalBtn.addEventListener('click', closeAuditModal);
  if (auditModal) {
    auditModal.addEventListener('click', (e) => {
      if (e.target === auditModal) closeAuditModal();
    });
  }

  // 4. Database Submission & WhatsApp Direct Routing
  const handleLeadSubmit = (formId, isModal = false) => {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const name = form.querySelector('[name="client_name"]')?.value || 'Client';
      const phone = form.querySelector('[name="client_phone"]')?.value || 'N/A';
      const brand = form.querySelector('[name="brand_name"]')?.value || 'New Venture';
      const service = form.querySelector('[name="service_type"]')?.value || 'Digital Dominance Growth Plan';
      const budget = form.querySelector('[name="budget"]')?.value || '$100 - $300';
      const message = form.querySelector('[name="message"]')?.value || 'I want to scale my brand with NEX-47.';
      const source = isModal ? 'Hero/Header Modal' : 'Main Contact Section';

      // 1. Save to MySQL Database via PHP API
      try {
        fetch('api/submit_lead.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            client_name: name,
            client_phone: phone,
            brand_name: brand,
            service_type: service,
            budget: budget,
            message: message,
            source: source
          })
        }).then(res => res.json()).then(data => {
          console.log('Lead stored in MySQL:', data);
        }).catch(err => {
          console.log('AJAX sync fallback:', err);
        });
      } catch (err) {
        console.error('Database lead save error:', err);
      }

      // 2. Open WhatsApp with formatted brief
      const whatsappNumber = '923495438083'; // Official Agency WhatsApp Number

      const text = encodeURIComponent(
        `⚡ *New Client Inquiry — NEX - 47 CATALYS'S*\n\n` +
        `👤 *Name:* ${name}\n` +
        `📱 *Phone/WhatsApp:* ${phone}\n` +
        `🏢 *Brand/Website:* ${brand}\n` +
        `🎯 *Service Required:* ${service}\n` +
        `💰 *Budget Tier:* ${budget}\n` +
        `💬 *Message:* ${message}\n\n` +
        `_Stored in NEX-47 Database & CRM_`
      );

      const whatsappUrl = `https://wa.me/${whatsappNumber}?text=${text}`;

      // Open WhatsApp in new tab
      window.open(whatsappUrl, '_blank');

      // Success feedback
      showToast('Growth brief saved to database & opening WhatsApp! 🚀');

      if (isModal) {
        setTimeout(() => {
          closeAuditModal();
          form.reset();
        }, 1200);
      } else {
        form.reset();
      }
    });
  };

  handleLeadSubmit('modalAuditForm', true);
  handleLeadSubmit('mainContactForm', false);

  // 5. Toast Notification System
  function showToast(message) {
    let toast = document.getElementById('nexToast');
    if (!toast) {
      toast = document.createElement('div');
      toast.id = 'nexToast';
      toast.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-[1000] px-6 py-3 rounded-full bg-[#11121a] border border-[#39ff14] text-white text-sm font-medium shadow-[0_0_25px_rgba(57,255,20,0.4)] flex items-center gap-2 transition-all duration-300 opacity-0 pointer-events-none';
      document.body.appendChild(toast);
    }

    toast.innerHTML = `<span class="h-2 w-2 rounded-full bg-[#39ff14] animate-ping"></span> ${message}`;
    toast.classList.remove('opacity-0', 'pointer-events-none');
    toast.classList.add('opacity-100');

    setTimeout(() => {
      toast.classList.add('opacity-0', 'pointer-events-none');
      toast.classList.remove('opacity-100');
    }, 4000);
  }

  // 6. FAQ Accordion Toggle
  const faqItems = document.querySelectorAll('.faq-item');
  faqItems.forEach(item => {
    const trigger = item.querySelector('.faq-trigger');
    const content = item.querySelector('.faq-content');
    const icon = item.querySelector('.faq-icon');

    if (trigger && content) {
      trigger.addEventListener('click', () => {
        const isOpen = !content.classList.contains('hidden');

        // Close all others
        faqItems.forEach(other => {
          const otherContent = other.querySelector('.faq-content');
          const otherIcon = other.querySelector('.faq-icon');
          if (otherContent) otherContent.classList.add('hidden');
          if (otherIcon) otherIcon.classList.remove('rotate-180', 'text-[#39ff14]');
        });

        if (!isOpen) {
          content.classList.remove('hidden');
          if (icon) {
            icon.classList.add('rotate-180', 'text-[#39ff14]');
          }
        }
      });
    }
  });

  // 7. Filter Tabs for 9 Services
  const filterBtns = document.querySelectorAll('.service-filter-btn');
  const serviceCards = document.querySelectorAll('.service-card-item');

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => {
        b.classList.remove('bg-[#39ff14]', 'text-[#060608]', 'font-bold', 'border-[#39ff14]');
        b.classList.add('bg-white/5', 'text-white/70', 'border-white/10');
      });

      btn.classList.add('bg-[#39ff14]', 'text-[#060608]', 'font-bold', 'border-[#39ff14]');
      btn.classList.remove('bg-white/5', 'text-white/70', 'border-white/10');

      const filter = btn.getAttribute('data-filter');

      serviceCards.forEach(card => {
        const category = card.getAttribute('data-category');
        if (filter === 'all' || category.includes(filter)) {
          card.style.display = 'block';
        } else {
          card.style.display = 'none';
        }
      });
    });
  });

  // 8. Sticky Header Scroll Effect
  const mainHeader = document.getElementById('mainHeader');
  window.addEventListener('scroll', () => {
    if (window.scrollY > 40) {
      mainHeader?.classList.add('bg-[#060608]/90', 'backdrop-blur-md', 'border-white/10', 'shadow-[0_4px_30px_rgba(0,0,0,0.8)]');
      mainHeader?.classList.remove('bg-transparent', 'border-transparent');
    } else {
      mainHeader?.classList.remove('bg-[#060608]/90', 'backdrop-blur-md', 'border-white/10', 'shadow-[0_4px_30px_rgba(0,0,0,0.8)]');
      mainHeader?.classList.add('bg-transparent', 'border-transparent');
    }
  });
});

  </script>
</body>
</html>

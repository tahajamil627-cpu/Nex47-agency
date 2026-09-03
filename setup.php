<?php
/**
 * NEX - 47 CATALYS'S - 1-Click Database Setup & Migration Script
 */

require_once __DIR__ . '/api/config.php';

$isCli = (php_sapi_name() === 'cli');

try {
    $pdo = getDBConnection();
    
    // Ensure leads table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `leads` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `client_name` VARCHAR(255) NOT NULL,
        `client_phone` VARCHAR(50) NOT NULL,
        `brand_name` VARCHAR(255) DEFAULT NULL,
        `service_type` VARCHAR(150) NOT NULL,
        `budget` VARCHAR(100) DEFAULT '$1,000 - $3,000',
        `message` TEXT DEFAULT NULL,
        `lead_source` VARCHAR(50) DEFAULT 'Website Form',
        `status` VARCHAR(50) DEFAULT 'new',
        `notes` TEXT DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Seed initial leads if empty
    $count = $pdo->query("SELECT COUNT(*) FROM `leads`")->fetchColumn();
    if ($count == 0) {
        $stmt = $pdo->prepare("INSERT INTO `leads` (`client_name`, `client_phone`, `brand_name`, `service_type`, `budget`, `message`, `status`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Zubair Ahmed', '+923001234567', 'Aura Luxury Apparel', 'Meta Ads / Facebook Ads', '$3,000 - $7,500', 'Looking to scale ROAS from 2.5x to 5x.', 'new']);
        $stmt->execute(['Hamza Tariq', '+923219876543', 'Apex Real Estate', 'Lead Generation & Funnels', '$7,500 - $20,000', 'Need WhatsApp lead generation funnel.', 'contacted']);
        $stmt->execute(['Sara Mansoor', '+923334445566', 'Glow Skin Studio', 'Social Media Marketing (SMM)', '$1,000 - $3,000', 'Need viral reels production.', 'in_progress']);
    }

    $leadCount = $pdo->query("SELECT COUNT(*) FROM `leads`")->fetchColumn();

    if ($isCli) {
        echo "========================================================\n";
        echo "⚡ NEX - 47 CATALYS'S: Database & Tables Ready!\n";
        echo "✅ Database `nex47_db` is active & ready.\n";
        echo "✅ Leads Table verified. Current records: $leadCount\n";
        echo "✅ Admin Dashboard URL: http://localhost/NEX/admin/\n";
        echo "========================================================\n";
        exit;
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>NEX-47 | Database Ready</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Space+Grotesk:wght@700&family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
</head>
<body class="bg-[#060608] text-white min-h-screen flex items-center justify-center p-5 font-['Inter']">
  <div class="max-w-lg w-full bg-[#0c0d14] border-2 border-[#39ff14]/50 rounded-2xl p-8 text-center shadow-[0_0_50px_rgba(57,255,20,0.25)]">
    
    <div class="w-16 h-16 rounded-2xl bg-black border border-[#39ff14] mx-auto flex items-center justify-center p-2 mb-4 shadow-[0_0_20px_rgba(57,255,20,0.4)]">
      <img src="assets/logo.png" alt="NEX-47" class="w-full h-full object-contain">
    </div>

    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#39ff14]/10 border border-[#39ff14]/30 text-[#39ff14] text-xs font-mono uppercase tracking-widest mb-3">
      <span class="w-2 h-2 rounded-full bg-[#39ff14] animate-ping"></span>
      <span>SYSTEM OPERATIONAL</span>
    </div>

    <h1 class="font-['Space_Grotesk'] font-bold text-2xl text-white">Database Initialized!</h1>
    <p class="text-xs text-gray-400 mt-1">MySQL Database <code class="text-[#39ff14] font-mono">nex47_db</code> is connected and all tables are ready.</p>

    <div class="mt-6 bg-black/60 rounded-xl p-4 border border-white/10 text-left font-mono text-xs space-y-2 text-gray-300">
      <div class="flex justify-between border-b border-white/5 pb-2">
        <span class="text-gray-500">Database Name:</span>
        <span class="text-[#39ff14]">nex47_db</span>
      </div>
      <div class="flex justify-between border-b border-white/5 pb-2">
        <span class="text-gray-500">Active Table:</span>
        <span class="text-white">leads</span>
      </div>
      <div class="flex justify-between">
        <span class="text-gray-500">Total Records:</span>
        <span class="text-[#00f0ff] font-bold"><?= $leadCount ?> Inquiries</span>
      </div>
    </div>

    <div class="mt-8 flex flex-col sm:flex-row gap-3">
      <a href="admin/" class="flex-1 py-3 rounded-full bg-[#39ff14] text-black font-bold text-xs uppercase font-mono tracking-wider hover:bg-white transition-all flex items-center justify-center gap-2 shadow-[0_0_20px_rgba(57,255,20,0.3)]">
        <i class="fa-solid fa-gauge-high"></i>
        <span>Open Admin CRM</span>
      </a>
      <a href="index.html" class="flex-1 py-3 rounded-full bg-white/5 border border-white/20 text-white font-semibold text-xs uppercase font-mono tracking-wider hover:border-[#39ff14] hover:text-[#39ff14] transition-all flex items-center justify-center gap-2">
        <i class="fa-solid fa-globe"></i>
        <span>View Website</span>
      </a>
    </div>

  </div>
</body>
</html>

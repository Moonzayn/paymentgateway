<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deploy - PPOB Express</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
    </style>
</head>
<body class="flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md w-full mx-4">
        <div class="text-center">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-check text-4xl text-green-500"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Deploy Berhasil!</h1>
            <p class="text-gray-500 mb-6">File sudah berhasil di-deploy ke server.</p>
            <div class="bg-gray-50 rounded-lg p-4 text-left mb-6">
                <p class="text-sm text-gray-500 mb-1">Waktu Deploy:</p>
                <p class="font-mono text-gray-800"><?= date('Y-m-d H:i:s') ?></p>
            </div>
            <a href="/" class="inline-block bg-purple-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-purple-700 transition">
                <i class="fas fa-home mr-2"></i>Kembali ke Home
            </a>
        </div>
    </div>
</body>
</html>

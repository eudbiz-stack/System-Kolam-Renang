<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Scanner - Gate Entry</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- QR Code Scanner Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Arial', sans-serif;
        }

        .scanner-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        #qr-reader {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            border-radius: 10px;
            overflow: hidden;
        }

        .ticket-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }

        .valid-ticket {
            border-left: 5px solid #28a745;
            background: #d4edda;
        }

        .invalid-ticket {
            border-left: 5px solid #dc3545;
            background: #f8d7da;
        }

        .info-table {
            width: 100%;
            margin-top: 15px;
        }

        .info-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #dee2e6;
        }

        .info-table td:first-child {
            font-weight: bold;
            width: 35%;
            background: rgba(0, 0, 0, 0.05);
        }

        .status-badge {
            font-size: 1.1rem;
            padding: 8px 16px;
        }

        .scanner-controls {
            text-align: center;
            margin: 20px 0;
        }

        #scan-result {
            min-height: 100px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="scanner-container">
            <!-- Header -->
            <div class="text-center mb-4">
                <h2 class="text-primary">
                    <i class="fas fa-qrcode me-3"></i>QR Code Scanner
                </h2>
                <p class="text-muted">Kolam Renang Licia - Gate Entry System</p>
            </div>

            <!-- Scanner Controls -->
            <div class="scanner-controls">
                <button id="start-scan" class="btn btn-success btn-lg me-3">
                    <i class="fas fa-camera me-2"></i>Start Scanning
                </button>
                <button id="stop-scan" class="btn btn-danger btn-lg" disabled>
                    <i class="fas fa-stop me-2"></i>Stop Scanning
                </button>
            </div>

            <!-- QR Reader -->
            <div id="qr-reader" class="mb-4"></div>

            <!-- Scan Result -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-ticket-alt me-2"></i>Scan Result
                    </h5>
                </div>
                <div class="card-body">
                    <div id="scan-result">
                        <span>Scan QR Code untuk melihat informasi tiket</span>
                    </div>
                </div>
            </div>

            <!-- Recent Scans -->
            <div class="card mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Recent Scans
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="clearHistory()">
                        <i class="fas fa-trash me-1"></i>Clear
                    </button>
                </div>
                <div class="card-body">
                    <div id="scan-history">
                        <div class="text-center text-muted">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>Belum ada scan history</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let html5QrcodeScanner;
        let scanHistory = [];

        // Initialize scanner
        function startScanner() {
            html5QrcodeScanner = new Html5QrcodeScanner(
                "qr-reader", {
                    fps: 10,
                    qrbox: {
                        width: 250,
                        height: 250
                    },
                    rememberLastUsedCamera: true,
                    // Only use back camera
                    aspectRatio: 1.777778
                },
                false
            );

            html5QrcodeScanner.render(onScanSuccess, onScanFailure);

            document.getElementById('start-scan').disabled = true;
            document.getElementById('stop-scan').disabled = false;
        }

        function stopScanner() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
                html5QrcodeScanner = null;
            }

            document.getElementById('start-scan').disabled = false;
            document.getElementById('stop-scan').disabled = true;
        }

        function onScanSuccess(decodedText, decodedResult) {
            try {
                // Try to parse as JSON (our ticket format)
                const ticketData = JSON.parse(decodedText);
                displayTicketInfo(ticketData, true);
                addToHistory(ticketData, true);

                // Play success sound (optional)
                playSuccessSound();

            } catch (error) {
                // If not JSON, treat as regular text
                displayGenericQR(decodedText);
                addToHistory({
                    raw: decodedText
                }, false);
            }
        }

        function onScanFailure(error) {
            // Handle scan failure - usually just ignore
            // console.warn(`Code scan error = ${error}`);
        }

        function displayTicketInfo(ticketData, isValid) {
            const resultDiv = document.getElementById('scan-result');
            const currentTime = new Date();
            const scanTime = currentTime.toLocaleString('id-ID');

            // Validate ticket
            const isTicketValid = validateTicket(ticketData);
            const statusClass = isTicketValid ? 'valid-ticket' : 'invalid-ticket';
            const statusBadge = isTicketValid ? 'success' : 'danger';
            const statusText = isTicketValid ? '✓ TIKET VALID' : '✗ TIKET TIDAK VALID';

            resultDiv.innerHTML = `
                <div class="ticket-info ${statusClass}">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Informasi Tiket</h5>
                        <span class="badge bg-${statusBadge} status-badge">${statusText}</span>
                    </div>
                    
                    <table class="info-table">
                        <tr>
                            <td>Kode Booking</td>
                            <td><strong>${ticketData.code || '-'}</strong></td>
                        </tr>
                        <tr>
                            <td>Nama Pelanggan</td>
                            <td>${ticketData.name || '-'}</td>
                        </tr>
                        <tr>
                            <td>Tanggal Kunjungan</td>
                            <td>${ticketData.date || '-'}</td>
                        </tr>
                        <tr>
                            <td>Jam Kunjungan</td>
                            <td>${ticketData.time || '-'}</td>
                        </tr>
                        <tr>
                            <td>Jumlah Tiket</td>
                            <td>${ticketData.qty || '-'} orang</td>
                        </tr>
                        <tr>
                            <td>Harga</td>
                            <td><strong>${ticketData.price || '-'}</strong></td>
                        </tr>
                        <tr>
                            <td>Waktu Scan</td>
                            <td>${scanTime}</td>
                        </tr>
                    </table>
                    
                    ${isTicketValid ? 
                        '<div class="alert alert-success mt-3 mb-0"><i class="fas fa-check-circle me-2"></i>Tiket valid, pelanggan diizinkan masuk</div>' :
                        '<div class="alert alert-danger mt-3 mb-0"><i class="fas fa-times-circle me-2"></i>Tiket tidak valid atau sudah expired</div>'
                    }
                </div>
            `;
        }

        function displayGenericQR(text) {
            const resultDiv = document.getElementById('scan-result');
            const currentTime = new Date().toLocaleString('id-ID');

            resultDiv.innerHTML = `
                <div class="ticket-info invalid-ticket">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">QR Code Data</h5>
                        <span class="badge bg-warning status-badge">⚠ BUKAN TIKET</span>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Raw Data:</strong><br>
                        <code>${text}</code>
                    </div>
                    
                    <small class="text-muted">Scan time: ${currentTime}</small>
                </div>
            `;
        }

        function validateTicket(ticketData) {
            // Check required fields
            if (!ticketData.code || !ticketData.name || !ticketData.date) {
                return false;
            }

            // Check if ticket is marked as valid
            if (ticketData.valid !== true) {
                return false;
            }

            // Check timestamp (tickets older than 24 hours might be invalid)
            if (ticketData.timestamp) {
                const ticketTime = new Date(ticketData.timestamp);
                const now = new Date();
                const diffHours = (now - ticketTime) / (1000 * 60 * 60);

                if (diffHours > 24) {
                    return false;
                }
            }

            // Additional validation can be added here
            // - Check against database
            // - Verify digital signature
            // - Check if ticket already used

            return true;
        }

        function addToHistory(ticketData, isValid) {
            const historyItem = {
                data: ticketData,
                isValid: isValid,
                scanTime: new Date().toLocaleString('id-ID'),
                timestamp: Date.now()
            };

            scanHistory.unshift(historyItem);

            // Keep only last 10 items
            if (scanHistory.length > 10) {
                scanHistory = scanHistory.slice(0, 10);
            }

            updateHistoryDisplay();
        }

        function updateHistoryDisplay() {
            const historyDiv = document.getElementById('scan-history');

            if (scanHistory.length === 0) {
                historyDiv.innerHTML = `
                    <div class="text-center text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>Belum ada scan history</p>
                    </div>
                `;
                return;
            }

            let historyHTML = '';
            scanHistory.forEach((item, index) => {
                const statusClass = item.isValid && validateTicket(item.data) ? 'success' : 'danger';
                const statusIcon = item.isValid && validateTicket(item.data) ? 'check-circle' : 'times-circle';

                historyHTML += `
                    <div class="border-bottom py-2 ${index === 0 ? 'border-top' : ''}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${item.data.code || item.data.raw || 'Unknown'}</strong>
                                ${item.data.name ? `<br><small class="text-muted">${item.data.name}</small>` : ''}
                            </div>
                            <div class="text-end">
                                <i class="fas fa-${statusIcon} text-${statusClass} me-2"></i>
                                <small class="text-muted">${item.scanTime}</small>
                            </div>
                        </div>
                    </div>
                `;
            });

            historyDiv.innerHTML = historyHTML;
        }

        function clearHistory() {
            scanHistory = [];
            updateHistoryDisplay();
        }

        function playSuccessSound() {
            // Create a simple beep sound
            const audioContext = new(window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            oscillator.frequency.value = 800;
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.3);
        }

        // Event listeners
        document.getElementById('start-scan').addEventListener('click', startScanner);
        document.getElementById('stop-scan').addEventListener('click', stopScanner);

        // Auto-start scanner when page loads (optional)
        // window.addEventListener('load', startScanner);
    </script>
</body>

</html>
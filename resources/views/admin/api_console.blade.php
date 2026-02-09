<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RozGold Developer Console</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #475569;
            --success-color: #16a34a;
            --danger-color: #dc2626;
            --warning-color: #d97706;
            --info-color: #0891b2;
            --sidebar-width: 300px;
            --header-height: 60px;
            --font-main: 'Inter', sans-serif;
            --font-code: 'JetBrains Mono', monospace;
        }

        [data-bs-theme="dark"] {
            --bg-body: #0f172a;
            --bg-panel: #1e293b;
            --border-color: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --code-bg: #0f172a;
        }

        [data-bs-theme="light"] {
            --bg-body: #f8fafc;
            --bg-panel: #ffffff;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --code-bg: #f1f5f9;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-body);
            color: var(--text-main);
            height: 100vh;
            overflow: hidden;
        }

        /* Layout */
        .app-container {
            display: flex;
            height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--bg-panel);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
        }

        .sidebar-header {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            color: var(--primary-color);
        }

        .sidebar-search {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 0.5rem;
        }

        .endpoint-group-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin: 1rem 0.5rem 0.5rem;
            font-weight: 600;
        }

        .endpoint-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-bottom: 2px;
            font-size: 0.9rem;
        }

        .endpoint-item:hover {
            background-color: rgba(37, 99, 235, 0.1);
        }

        .endpoint-item.active {
            background-color: rgba(37, 99, 235, 0.15);
            color: var(--primary-color);
            font-weight: 500;
        }

        .method-badge {
            font-family: var(--font-code);
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.1rem 0.3rem;
            border-radius: 0.25rem;
            min-width: 45px;
            text-align: center;
            margin-right: 0.75rem;
        }

        .badge-get { background-color: rgba(22, 163, 74, 0.15); color: var(--success-color); }
        .badge-post { background-color: rgba(245, 158, 11, 0.15); color: var(--warning-color); }
        .badge-put { background-color: rgba(37, 99, 235, 0.15); color: var(--primary-color); }
        .badge-delete { background-color: rgba(220, 38, 38, 0.15); color: var(--danger-color); }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background-color: var(--bg-body);
        }

        .navbar {
            height: var(--header-height);
            background-color: var(--bg-panel);
            border-bottom: 1px solid var(--border-color);
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Request Section */
        .request-area {
            padding: 1.5rem;
            overflow-y: auto;
            flex: 1;
        }

        .url-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            background: var(--bg-panel);
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .method-select {
            width: 120px;
            font-family: var(--font-code);
            font-weight: 600;
            border: 1px solid var(--border-color);
            background-color: var(--bg-body);
        }

        .url-input {
            flex: 1;
            font-family: var(--font-code);
            border: 1px solid var(--border-color);
            background-color: var(--bg-body);
        }

        /* Panels */
        .panel {
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .panel-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .panel-title {
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }

        /* Code Editors */
        .code-editor {
            width: 100%;
            min-height: 200px;
            font-family: var(--font-code);
            font-size: 0.85rem;
            background-color: var(--code-bg);
            color: var(--text-main);
            border: none;
            padding: 1rem;
            resize: vertical;
            line-height: 1.5;
            outline: none;
        }

        .response-viewer {
            min-height: 300px;
            max-height: 600px;
            overflow: auto;
            margin: 0;
            border-radius: 0 0 0.5rem 0.5rem;
        }

        /* Syntax Highlighting */
        .json-key { color: #d97706; }
        .json-string { color: #16a34a; }
        .json-number { color: #2563eb; }
        .json-boolean { color: #dc2626; }
        .json-null { color: #9333ea; }

        /* Loader */
        .spinner-track {
            display: none;
            margin-left: 0.5rem;
        }
    </style>
</head>
<body>

<div class="app-container">
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-cube me-2"></i> RozGold <span class="badge bg-primary ms-2" style="font-size: 0.6rem;">API v1</span>
        </div>
        
        <div class="sidebar-search">
            <input type="text" class="form-control form-control-sm" id="searchEndpoints" placeholder="Search endpoints...">
        </div>

        <div class="d-flex border-bottom">
            <button class="btn btn-sm btn-link text-decoration-none flex-fill active py-2" id="tab-collection" onclick="switchSidebarTab('collection')">Collection</button>
            <button class="btn btn-sm btn-link text-decoration-none flex-fill py-2 text-muted" id="tab-history" onclick="switchSidebarTab('history')">History</button>
        </div>

        <div class="sidebar-content" id="sidebarList">
            <!-- Populated by JS -->
        </div>
        
        <div class="p-3 border-top bg-panel">
            <small class="text-muted d-block mb-2">Environment</small>
            <select class="form-select form-select-sm" id="environmentSelector">
                <option value="https://rozgold.techotd.in" selected>Production (rozgold.techotd.in)</option>
                <option value="{{ url('/') }}">Current Server ({{ url('/') }})</option>
                <option value="http://localhost:8000">Localhost (Draft)</option>
            </select>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Navbar -->
        <nav class="navbar">
            <div class="d-flex align-items-center">
                <span class="text-muted small me-2">Active Endpoint:</span>
                <span class="fw-semibold" id="activeEndpointName">Select an endpoint</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleTheme()" id="themeBtn">
                    <i class="fas fa-moon"></i>
                </button>
                <a href="{{ url('/admin/dashboard') }}" class="btn btn-sm btn-primary">
                    Back to Dashboard
                </a>
            </div>
        </nav>

        <!-- Request Area -->
        <div class="request-area">
            
            <!-- URL Bar -->
            <div class="url-bar">
                <select class="form-select method-select" id="httpMethod">
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="DELETE">DELETE</option>
                </select>
                <input type="text" class="form-control url-input" id="urlInput" placeholder="Enter request URL">
                <button class="btn btn-primary px-4 fw-medium" onclick="sendRequest()" id="sendBtn">
                    <span class="spinner-border spinner-border-sm spinner-track" role="status" aria-hidden="true"></span>
                    <span class="btn-text">Send</span>
                </button>
            </div>

            <div class="row h-100">
                <!-- Request Config -->
                <div class="col-lg-6 d-flex flex-column h-100">
                    <div class="panel flex-grow-0">
                        <div class="panel-header">
                            <ul class="nav nav-pills card-header-pills" id="reqTabs">
                                <li class="nav-item"><a class="nav-link active py-1 px-3 fs-7" data-bs-toggle="pill" href="#tab-body">Body</a></li>
                                <li class="nav-item"><a class="nav-link py-1 px-3 fs-7" data-bs-toggle="pill" href="#tab-headers">Headers</a></li>
                                <li class="nav-item"><a class="nav-link py-1 px-3 fs-7" data-bs-toggle="pill" href="#tab-auth">Auth</a></li>
                            </ul>
                            <div class="ms-auto">
                                <button class="btn btn-xs btn-link text-muted p-0" onclick="formatJSON()" title="Format JSON"><i class="fas fa-align-left"></i></button>
                            </div>
                        </div>
                        <div class="card-body p-0 tab-content">
                            <!-- JSON Body -->
                            <div class="tab-pane fade show active" id="tab-body">
                                <textarea class="code-editor" id="jsonBody" placeholder="// Enter JSON request body here..."></textarea>
                            </div>
                            
                            <!-- Headers -->
                            <div class="tab-pane fade" id="tab-headers">
                                <div class="p-3">
                                    <div class="input-group mb-2">
                                        <span class="input-group-text bg-light">Accept</span>
                                        <input type="text" class="form-control" value="application/json" readonly>
                                    </div>
                                    <div class="input-group mb-2">
                                        <span class="input-group-text bg-light">Content-Type</span>
                                        <input type="text" class="form-control" value="application/json" readonly>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">X-CSRF-TOKEN</span>
                                        <input type="text" class="form-control" value="{{ csrf_token() }}" readonly>
                                    </div>
                                </div>
                            </div>

                            <!-- Auth -->
                            <div class="tab-pane fade" id="tab-auth">
                                <div class="p-3">
                                    <label class="form-label fw-medium small text-muted text-uppercase">Bearer Token</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control font-monospace" id="authToken" placeholder="Paste JWT Token here">
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('authToken')">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="form-text small mt-2">Token is automatically saved to local storage.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description / Docs placeholder -->
                    <div class="alert alert-light border small text-muted">
                        <i class="fas fa-info-circle me-1"></i> <span id="endpointDesc">Select an endpoint to see its description and requirements.</span>
                    </div>
                </div>

                <!-- Response Config -->
                <div class="col-lg-6 d-flex flex-column h-100 pb-5">
                    <div class="panel h-100 d-flex flex-column">
                        <div class="panel-header bg-light">
                            <span class="panel-title">Response</span>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-secondary" id="statusBadge">STATUS</span>
                                <span class="small text-muted" id="timeBadge">0 ms</span>
                                <button class="btn btn-xs btn-outline-secondary" onclick="copyResponse()">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>
                        <div class="position-relative flex-fill overflow-hidden bg-code">
                             <div id="responseEmpty" class="position-absolute top-50 start-50 translate-middle text-center text-muted">
                                <i class="fas fa-paper-plane fa-2x mb-2 opacity-50"></i>
                                <p class="small m-0">Ready to send request</p>
                            </div>
                            <pre class="response-viewer p-3 h-100 m-0" id="responseOutput"></pre>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Configuration & Data
    const API_BASE = "https://rozgold.techotd.in/api";
    
    // Comprehensive Endpoint Definitions
    const endpoints = [
        {
            group: 'OTP (Public)',
            items: [
                { name: 'Send OTP', method: 'POST', path: '/send-otp', desc: 'Send login OTP to mobile number', body: { mobiles: '6203454484' } },
                { name: 'Verify OTP', method: 'POST', path: '/verify-otp', desc: 'Verify OTP and get auth token', body: { mobiles: '6203454484', otp: '123456' } },
                { name: 'Resend OTP', method: 'POST', path: '/resend-otp', desc: 'Resend OTP (2-min cooldown)', body: { mobiles: '6203454484' } },
            ]
        },
        {
            group: 'Authentication',
            items: [
                { name: 'Merchant Login', method: 'POST', path: '/auth/merchant-login', desc: 'Login for merchant/admin accounts', body: { email: 'Admin@rozgold.in', password: 'op4y#3#Xy%Ub`LM' } },
                { name: 'Register', method: 'POST', path: '/auth/register', desc: 'Register a new user', body: { name: 'John Doe', email: 'john@example.com', password: 'password123', password_confirmation: 'password123', mobile: '6203454484' } },
                { name: 'Send Email OTP', method: 'POST', path: '/auth/send-email-otp', desc: 'Send OTP to email address', body: { email: 'john@example.com' } },
                { name: 'Verify Email OTP', method: 'POST', path: '/auth/verify-email-otp', desc: 'Verify email OTP', body: { email: 'john@example.com', otp: '123456' } },
                { name: 'Logout', method: 'POST', path: '/auth/logout', desc: 'Invalidate current auth token (requires Bearer)', body: null },
            ]
        },
        {
            group: 'User Profile',
            items: [
                { name: 'Get Profile', method: 'GET', path: '/user/profile', desc: 'Get logged-in user details', body: null },
                { name: 'Update Profile', method: 'POST', path: '/user/profile', desc: 'Update user profile info', body: { name: 'John Doe', email: 'john@example.com' } },
                { name: 'Dashboard Stats', method: 'GET', path: '/user/dashboard', desc: 'Get wallet balance and summary stats', body: null },
                { name: 'Transaction History', method: 'GET', path: '/user/transactions', desc: 'List all user transactions', body: null },
            ]
        },
        {
            group: 'KYC & Onboarding',
            items: [
                { name: 'Verify PAN', method: 'POST', path: '/kyc/verify-pan', desc: 'Verify PAN number', body: { pan_number: 'ABCDE1234F' } },
                { name: 'Get KYC Status', method: 'POST', path: '/kyc/status', desc: 'Check current KYC progress', body: { mobile: '6203454484' } },
                { name: 'Submit KYC', method: 'POST', path: '/kyc/submit', desc: 'Final submit verified KYC data to Augmont', body: { pan_number: 'ABCDE1234F', aadhaar_number: '123412341234' } },
                { name: 'Generate Aadhaar OTP', method: 'POST', path: '/kyc/aadhaar/generate-otp', desc: 'Trigger Aadhaar e-KYC OTP', body: { aadhaar_number: '123412341234' } },
                { name: 'Verify Aadhaar OTP', method: 'POST', path: '/kyc/aadhaar/verify-otp', desc: 'Verify Aadhaar OTP and finalize', body: { request_id: 'REQ_ID_FROM_GENERATE', otp: '123456' } },
            ]
        },
        {
            group: 'Rates (Public)',
            items: [
                { name: 'Live Rates', method: 'GET', path: '/rates/live', desc: 'Get current gold/silver buy & sell rates', body: null },
                { name: 'Historical Rates', method: 'GET', path: '/rates/historical', desc: 'Get historical rates (query: fromDate, toDate, metalType, days)', body: null },
            ]
        },
        {
            group: 'Gold Operations',
            items: [
                { name: 'Get Gold Rates', method: 'GET', path: '/gold/rates', desc: 'Fetch current buy/sell rates (auth required)', body: null },
                { name: 'Buy Info', method: 'GET', path: '/gold/buy-info', desc: 'Pre-purchase information', body: null },
                { name: 'Buy Gold', method: 'POST', path: '/gold/buy', desc: 'Purchase gold', body: { metalType: 'gold', amount: 100, lockPrice: 6540.50, blockId: '240213123456_1', merchantTransactionId: 'TXN_' + Date.now() } },
                { name: 'Buy Invoice', method: 'POST', path: '/gold/buy-invoice', desc: 'Get invoice for a buy transaction', body: { transactionId: 'TXN_123' } },
                { name: 'Buy List', method: 'GET', path: '/gold/buy-list', desc: 'List buy transactions', body: null },
                { name: 'Sell Info', method: 'GET', path: '/gold/sell-info', desc: 'Pre-sell information', body: null },
                { name: 'Sell Gold', method: 'POST', path: '/gold/sell', desc: 'Sell gold from vault', body: { metalType: 'gold', quantity: 0.5, lockPrice: 6400.00, blockId: '240213123456_1', merchantTransactionId: 'TXN_' + Date.now() } },
                { name: 'Sell Invoice', method: 'POST', path: '/gold/sell-invoice', desc: 'Get invoice for a sell transaction', body: { transactionId: 'TXN_123' } },
                { name: 'Sell List', method: 'GET', path: '/gold/sell-list', desc: 'List sell transactions', body: null },
                { name: 'Update Txn Status', method: 'POST', path: '/gold/update-transaction-status', desc: 'Update transaction status after payment', body: { transactionId: 'TXN_123', status: 'success' } },
            ]
        },
        {
            group: 'Wallet',
            items: [
                { name: 'Wallet Balance', method: 'GET', path: '/wallet/balance', desc: 'Get current wallet balance', body: null },
                { name: 'Add Money', method: 'POST', path: '/wallet/add-money', desc: 'Add funds to wallet', body: { amount: 500 } },
                { name: 'Verify Payment', method: 'POST', path: '/wallet/verify-payment', desc: 'Verify payment after gateway callback', body: { orderId: 'ORDER_123', paymentId: 'PAY_123' } },
                { name: 'Withdraw', method: 'POST', path: '/wallet/withdraw', desc: 'Withdraw funds from wallet to bank', body: { amount: 500, bankId: 1 } },
                { name: 'Wallet Transactions', method: 'GET', path: '/wallet/transactions', desc: 'List wallet transaction history', body: null },
            ]
        },
        {
            group: 'Payment',
            items: [
                { name: 'Create Order', method: 'POST', path: '/payment/create-order', desc: 'Create a payment order', body: { amount: 1000, purpose: 'gold_purchase' } },
                { name: 'Order Status', method: 'GET', path: '/payment/order-status', desc: 'Check payment order status (query: orderId)', body: null },
            ]
        },
        {
            group: 'Local Banks',
            items: [
                { name: 'List Banks', method: 'GET', path: '/banks', desc: 'List user bank accounts (local DB)', body: null },
                { name: 'Add Bank', method: 'POST', path: '/banks/add', desc: 'Add a local bank account', body: { account_number: '1234567890', account_name: 'John Doe', ifsc_code: 'HDFC0001234', bank_name: 'HDFC Bank' } },
                { name: 'Verify Bank (Penny Drop)', method: 'POST', path: '/banks/verify', desc: 'Verify bank account via Cashfree Penny Drop. Send bank_id for saved account or account_number+ifsc_code for new.', body: { bank_id: 1 } },
                { name: 'Verify Bank (Direct)', method: 'POST', path: '/banks/verify', desc: 'Verify unsaved bank account directly', body: { account_number: '1234567890', ifsc_code: 'HDFC0001234', account_name: 'John Doe' } },
                { name: 'Update Bank', method: 'PUT', path: '/banks/1', desc: 'Update bank account by ID', body: { account_name: 'John Updated' } },
                { name: 'Delete Bank', method: 'DELETE', path: '/banks/1', desc: 'Delete bank account by ID', body: null },
            ]
        },
        {
            group: 'Master Data (Public)',
            items: [
                { name: 'States', method: 'GET', path: '/master/states', desc: 'List all Indian states', body: null },
                { name: 'Cities', method: 'GET', path: '/master/cities/1', desc: 'List cities for a state (stateId in URL)', body: null },
                { name: 'Banks List', method: 'GET', path: '/master/banks', desc: 'List all bank names', body: null },
            ]
        },
        {
            group: 'Augmont Users',
            items: [
                { name: 'Create Augmont User', method: 'POST', path: '/augmont/users', desc: 'Create user on Augmont platform', body: { mobile: '6203454484', email: 'user@example.com', name: 'John Doe', dob: '1990-01-01', userCity: 'Mumbai', userState: 'Maharashtra', userPincode: '400001', utmSource: 'app_v1' } },
                { name: 'Update Augmont User', method: 'PUT', path: '/augmont/users/{uniqueId}', desc: 'Update Augmont user details', body: { email: 'newemail@example.com', userCity: 'Pune' } },
                { name: 'User Passbook', method: 'GET', path: '/augmont/users/{uniqueId}/passbook', desc: 'View user passbook from Augmont', body: null },
            ]
        },
        {
            group: 'Augmont KYC',
            items: [
                { name: 'Get Augmont KYC', method: 'GET', path: '/augmont/kyc/{uniqueId}', desc: 'Get Augmont KYC status for user', body: null },
                { name: 'Update Augmont KYC', method: 'PUT', path: '/augmont/kyc/{uniqueId}', desc: 'Update Augmont KYC details', body: { panNumber: 'ABCDE1234F', dateOfBirth: '1990-01-01' } },
            ]
        },
        {
            group: 'Augmont Banks',
            items: [
                { name: 'Add Bank (Augmont)', method: 'POST', path: '/augmont/banks', desc: 'Add bank account on Augmont', body: { uniqueId: 'USER_UID', accountNumber: '1234567890', accountName: 'John Doe', ifscCode: 'HDFC0001234' } },
                { name: 'Get Banks (Augmont)', method: 'GET', path: '/augmont/banks/{uniqueId}', desc: 'List Augmont banks for user', body: null },
                { name: 'Validate IFSC', method: 'GET', path: '/augmont/banks/validate-ifsc/HDFC0001234', desc: 'Validate IFSC code', body: null },
                { name: 'Delete Bank (Augmont)', method: 'DELETE', path: '/augmont/banks/{uniqueId}/{bankId}', desc: 'Delete Augmont bank account', body: null },
            ]
        },
        {
            group: 'Augmont Addresses',
            items: [
                { name: 'Add Address', method: 'POST', path: '/augmont/addresses', desc: 'Add shipping address on Augmont', body: { uniqueId: 'USER_UID', name: 'Home', mobile: '6203454484', address: 'Flat 101, Residency', city: 'Mumbai', stateId: '12', pincode: '400001' } },
                { name: 'Get Addresses', method: 'GET', path: '/augmont/addresses/{uniqueId}', desc: 'List user addresses', body: null },
                { name: 'Delete Address', method: 'DELETE', path: '/augmont/addresses/{uniqueId}/{addressId}', desc: 'Delete an address', body: null },
            ]
        },
        {
            group: 'Augmont Orders',
            items: [
                { name: 'Create Order', method: 'POST', path: '/augmont/orders', desc: 'Create an Augmont order', body: { metalType: 'gold', amount: 100, lockPrice: 6540.50, blockId: 'BLOCK_123' } },
                { name: 'List Orders', method: 'GET', path: '/augmont/orders/{uniqueId}', desc: 'List orders for user', body: null },
                { name: 'Order Detail', method: 'GET', path: '/augmont/orders/{uniqueId}/{orderId}', desc: 'Get specific order details', body: null },
            ]
        },
        {
            group: 'Augmont Transfers',
            items: [
                { name: 'Create Transfer', method: 'POST', path: '/augmont/transfers', desc: 'Initiate a transfer', body: { uniqueId: 'USER_UID', metalType: 'gold', quantity: 0.5 } },
                { name: 'List Transfers', method: 'GET', path: '/augmont/transfers/{uniqueId}', desc: 'List transfers for user', body: null },
                { name: 'Transfer Detail', method: 'GET', path: '/augmont/transfers/{uniqueId}/{transferId}', desc: 'Get specific transfer details', body: null },
            ]
        },
        {
            group: 'Augmont Withdraw',
            items: [
                { name: 'Withdraw Info', method: 'GET', path: '/augmont/withdraw/{uniqueId}', desc: 'Get withdraw details and balance', body: null },
                { name: 'Withdraw Balance', method: 'GET', path: '/augmont/balance/{uniqueId}', desc: 'Get Augmont gold/silver balance', body: null },
                { name: 'Update Withdraw', method: 'PUT', path: '/augmont/withdraw', desc: 'Initiate withdrawal from Augmont', body: { uniqueId: 'USER_UID', metalType: 'gold', quantity: 0.5, bankId: 'BANK_123' } },
            ]
        },
        {
            group: 'Augmont Products (Public)',
            items: [
                { name: 'Product List', method: 'GET', path: '/augmont/products', desc: 'List redeemable products', body: null },
                { name: 'Product Detail', method: 'GET', path: '/augmont/products/{productId}', desc: 'Get specific product info', body: null },
            ]
        },
        {
            group: 'Augmont FD',
            items: [
                { name: 'FD Schemes', method: 'GET', path: '/augmont/fd/schemes', desc: 'List Gold FD schemes (public)', body: null },
                { name: 'FD Terms', method: 'GET', path: '/augmont/fd/terms', desc: 'Get FD terms and conditions', body: null },
                { name: 'Create FD', method: 'POST', path: '/augmont/fd', desc: 'Open a new Gold FD', body: { schemeId: 'SCHEME_123', goldWeight: 1.0 } },
                { name: 'FD Pre-Order', method: 'POST', path: '/augmont/fd/pre-order', desc: 'Pre-order check for FD', body: { schemeId: 'SCHEME_123', goldWeight: 1.0 } },
                { name: 'List User FDs', method: 'GET', path: '/augmont/fd/{uniqueId}', desc: 'List FDs for a user', body: null },
                { name: 'FD Detail', method: 'GET', path: '/augmont/fd/{uniqueId}/{fdId}', desc: 'Get specific FD details', body: null },
                { name: 'FD Passbook', method: 'GET', path: '/augmont/fd/{uniqueId}/{fdId}/passbook', desc: 'View FD passbook', body: null },
                { name: 'FD Transactions', method: 'GET', path: '/augmont/fd/{uniqueId}/transactions', desc: 'List FD transactions', body: null },
                { name: 'Close FD', method: 'POST', path: '/augmont/fd/{uniqueId}/{fdId}/close', desc: 'Close/break an FD', body: null },
                { name: 'Pre-Close Calc', method: 'POST', path: '/augmont/fd/{uniqueId}/{fdId}/pre-close-calc', desc: 'Calculate pre-closure penalty', body: null },
            ]
        },
        {
            group: 'Augmont SIP',
            items: [
                { name: 'SIP Rates', method: 'GET', path: '/augmont/sip/rates', desc: 'Check SIP specific rates (public)', body: null },
                { name: 'List SIPs', method: 'GET', path: '/augmont/sip', desc: 'List user SIP plans', body: null },
                { name: 'Create SIP', method: 'POST', path: '/augmont/sip', desc: 'Start a new SIP plan', body: { metalType: 'gold', amount: 500, frequency: 'monthly' } },
                { name: 'Update SIP', method: 'PUT', path: '/augmont/sip/{id}', desc: 'Update or pause a SIP', body: { status: 'paused' } },
            ]
        },
        {
            group: 'Invoices',
            items: [
                { name: 'Buy Invoice', method: 'GET', path: '/augmont/invoices/buy/{transactionId}', desc: 'Get buy transaction invoice', body: null },
                { name: 'Sell Invoice', method: 'GET', path: '/augmont/invoices/sell/{transactionId}', desc: 'Get sell transaction invoice', body: null },
                { name: 'Redeem Invoice', method: 'GET', path: '/augmont/invoices/redeem/{transactionId}', desc: 'Get redeem transaction invoice', body: null },
            ]
        },
        {
            group: 'Webhooks',
            items: [
                { name: 'Augmont User Create', method: 'POST', path: '/webhooks/augmont/user-create', desc: 'Augmont user creation webhook', body: { event: 'user.created', data: {} } },
                { name: 'Cashfree Payment', method: 'POST', path: '/webhooks/cashfree', desc: 'Cashfree payment callback', body: { orderId: 'ORDER_123', status: 'SUCCESS' } },
                { name: 'Transaction Status', method: 'POST', path: '/webhooks/transaction-status', desc: 'Transaction status update webhook', body: { transactionId: 'TXN_123', status: 'completed' } },
                { name: 'QuickeKYC Status', method: 'POST', path: '/webhooks/quickekyc-status', desc: 'QuickeKYC verification callback', body: { requestId: 'REQ_123', status: 'verified' } },
                { name: 'FD Closed', method: 'POST', path: '/webhooks/augmont/fd-closed', desc: 'FD closed webhook from Augmont', body: {} },
                { name: 'FD Matured', method: 'POST', path: '/webhooks/augmont/fd-matured', desc: 'FD matured webhook from Augmont', body: {} },
                { name: 'FD Monthly Interest', method: 'POST', path: '/webhooks/augmont/fd-monthly-interest', desc: 'FD monthly interest webhook', body: {} },
            ]
        },
    ];

    // State
    let currentEnv = localStorage.getItem('api_env') || document.getElementById('environmentSelector').value;
    let requestHistory = JSON.parse(localStorage.getItem('api_history') || '[]');

    // Initialization
    document.addEventListener('DOMContentLoaded', () => {
        renderSidebar();
        loadToken();
        setupTheme();
        
        // Select first endpoint by default if desktop
        if(window.innerWidth > 992 && endpoints.length > 0 && endpoints[0].items.length > 0) {
            // Optional: Auto select first item
            // loadEndpoint(endpoints[0].items[0]);
        }
        
        document.getElementById('environmentSelector').value = currentEnv || "{{ url('/') }}";
        
        document.getElementById('environmentSelector').addEventListener('change', (e) => {
            currentEnv = e.target.value;
            localStorage.setItem('api_env', currentEnv);
            // Updating current URL if one is selected
            const currentPath = document.getElementById('urlInput').dataset.path;
            if (currentPath) {
                const prefix = currentPath.startsWith('http') ? '' : (currentEnv + '/api');
                document.getElementById('urlInput').value = prefix + currentPath;
            }
        });

        document.getElementById('authToken').addEventListener('input', (e) => {
            localStorage.setItem('api_token', e.target.value);
        });

        document.getElementById('searchEndpoints').addEventListener('input', (e) => {
            renderSidebar(e.target.value);
        });
    });

    // UI Render Functions
    function renderSidebar(filter = '') {
        const container = document.getElementById('sidebarList');
        container.innerHTML = '';

        endpoints.forEach(group => {
            const visibleItems = group.items.filter(item => 
                item.name.toLowerCase().includes(filter.toLowerCase()) || 
                item.path.toLowerCase().includes(filter.toLowerCase())
            );

            if (visibleItems.length > 0) {
                const groupTitle = document.createElement('div');
                groupTitle.className = 'endpoint-group-title';
                groupTitle.textContent = group.group;
                container.appendChild(groupTitle);

                visibleItems.forEach(item => {
                    const el = document.createElement('div');
                    el.className = 'endpoint-item';
                    el.onclick = () => loadEndpoint(item, el);
                    
                    const methodClass = `badge-${item.method.toLowerCase()}`;
                    
                    el.innerHTML = `
                        <span class="method-badge ${methodClass}">${item.method}</span>
                        <span class="text-truncate">${item.name}</span>
                    `;
                    container.appendChild(el);
                });
            }
        });
    }

    function switchSidebarTab(tab) {
        document.getElementById('tab-collection').classList.toggle('active', tab === 'collection');
        document.getElementById('tab-history').classList.toggle('active', tab === 'history');
        document.getElementById('tab-collection').classList.toggle('text-muted', tab !== 'collection');
        document.getElementById('tab-history').classList.toggle('text-muted', tab !== 'history');

        if (tab === 'collection') {
            renderSidebar();
        } else {
            renderHistory();
        }
    }

    function renderHistory() {
        const container = document.getElementById('sidebarList');
        container.innerHTML = '';
        
        if (requestHistory.length === 0) {
            container.innerHTML = '<div class="text-center text-muted p-4 small">No request history</div>';
            return;
        }

        const title = document.createElement('div');
        title.className = 'endpoint-group-title';
        title.textContent = 'Recent Requests';
        container.appendChild(title);

        [...requestHistory].reverse().forEach(item => {
            const el = document.createElement('div');
            el.className = 'endpoint-item';
            el.onclick = () => loadEndpoint({
                name: item.name || 'Custom Request',
                method: item.method,
                path: item.path,
                body: item.body,
                desc: 'Replayed from history'
            }, el);
            
            const methodClass = `badge-${item.method.toLowerCase()}`;
            el.innerHTML = `
                <span class="method-badge ${methodClass}">${item.method}</span>
                <div class="d-flex flex-column overflow-hidden">
                    <span class="text-truncate small fw-medium">${item.path}</span>
                    <span class="text-muted" style="font-size: 0.7rem;">${new Date(item.time).toLocaleTimeString()}</span>
                </div>
            `;
            container.appendChild(el);
        });
    }

    // Logic Functions
    function loadEndpoint(item, element) {
        // Highlight active sidebar item
        document.querySelectorAll('.endpoint-item').forEach(el => el.classList.remove('active'));
        if (element) element.classList.add('active');

        // Set Values
        const env = document.getElementById('environmentSelector').value;
        const fullUrl = item.path.startsWith('http') ? item.path : (env + '/api' + item.path);
        
        document.getElementById('httpMethod').value = item.method;
        document.getElementById('urlInput').value = fullUrl;
        document.getElementById('urlInput').dataset.path = item.path; // Store relative path
        document.getElementById('activeEndpointName').textContent = item.name;
        document.getElementById('endpointDesc').textContent = item.desc || 'No description available.';

        // Set Body
        const bodyInput = document.getElementById('jsonBody');
        if (item.body) {
            bodyInput.value = JSON.stringify(item.body, null, 4);
        } else {
            bodyInput.value = '';
        }

        // Reset Response
        document.getElementById('responseOutput').innerHTML = '';
        document.getElementById('responseEmpty').classList.remove('d-none');
        document.getElementById('statusBadge').className = 'badge bg-secondary';
        document.getElementById('statusBadge').textContent = 'STATUS';
    }

    async function sendRequest() {
        const method = document.getElementById('httpMethod').value;
        const url = document.getElementById('urlInput').value;
        const token = document.getElementById('authToken').value;
        const bodyStr = document.getElementById('jsonBody').value;
        
        // UI Loading
        const btn = document.getElementById('sendBtn');
        const spinner = btn.querySelector('.spinner-track');
        btn.disabled = true;
        spinner.style.display = 'inline-block';
        document.getElementById('responseEmpty').classList.add('d-none');

        const startTime = Date.now();

        try {
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            };
            if (token) headers['Authorization'] = `Bearer ${token}`;

            const options = { method, headers };
            if (['POST', 'PUT', 'PATCH'].includes(method) && bodyStr.trim()) {
                try {
                    // Minify JSON before sending
                    options.body = JSON.stringify(JSON.parse(bodyStr));
                } catch(e) {
                    alert('Invalid JSON Body. Please check syntax.');
                    throw e;
                }
            }

            const res = await fetch(url, options);
            const duration = Date.now() - startTime;
            
            // Handle Response content
            const contentType = res.headers.get('content-type');
            let data;
            if (contentType && contentType.includes('application/json')) {
                data = await res.json();
            } else {
                data = await res.text();
            }

            renderResponse(res.status, res.statusText, duration, data);
            addToHistory(method, url, bodyStr);

        } catch (error) {
            console.error(error);
            renderResponse(0, 'Network Error', 0, { error: error.message });
        } finally {
            btn.disabled = false;
            spinner.style.display = 'none';
        }
    }

    function renderResponse(status, statusText, duration, data) {
        const viewer = document.getElementById('responseOutput');
        const badge = document.getElementById('statusBadge');
        
        document.getElementById('timeBadge').textContent = `${duration} ms`;
        badge.textContent = `${status} ${statusText}`;

        // Color coding status
        badge.className = 'badge ';
        if (status >= 200 && status < 300) badge.classList.add('bg-success');
        else if (status >= 400 && status < 500) badge.classList.add('bg-warning');
        else badge.classList.add('bg-danger');

        if (typeof data === 'object') {
            viewer.innerHTML = syntaxHighlight(data);
        } else {
            viewer.textContent = data;
        }
    }

    function syntaxHighlight(json) {
        if (typeof json != 'string') json = JSON.stringify(json, undefined, 4);
        json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
            var cls = 'json-number';
            if (/^"/.test(match)) {
                if (/:$/.test(match)) cls = 'json-key';
                else cls = 'json-string';
            } else if (/true|false/.test(match)) cls = 'json-boolean';
            else if (/null/.test(match)) cls = 'json-null';
            return '<span class="' + cls + '">' + match + '</span>';
        });
    }

    // Utilities
    function addToHistory(method, fullUrl, bodyStr) {
        // Extract relative path for display if possible
        let displayPath = fullUrl;
        try {
            const urlObj = new URL(fullUrl);
            displayPath = urlObj.pathname;
        } catch(e) {}

        const entry = {
            method,
            path: displayPath,
            name: document.getElementById('activeEndpointName').textContent, // Capture current context
            body: bodyStr ? JSON.parse(bodyStr) : null,
            time: Date.now()
        };

        // Add to front, limit to 20
        requestHistory = [entry, ...requestHistory.filter(h => h.time !== entry.time)].slice(0, 20);
        localStorage.setItem('api_history', JSON.stringify(requestHistory));
        
        // Refresh history view if active
        if (document.getElementById('tab-history').classList.contains('active')) {
            renderHistory();
        }
    }

    function formatJSON() {
        const el = document.getElementById('jsonBody');
        try {
            const val = JSON.parse(el.value);
            el.value = JSON.stringify(val, null, 4);
        } catch {
            // ignore if invalid
        }
    }

    function copyResponse() {
        const text = document.getElementById('responseOutput').innerText;
        navigator.clipboard.writeText(text);
    }

    function togglePassword(id) {
        const el = document.getElementById(id);
        el.type = el.type === 'password' ? 'text' : 'password';
    }

    function loadToken() {
        const t = localStorage.getItem('api_token');
        if (t) document.getElementById('authToken').value = t;
    }

    // Context Theme
    function setupTheme() {
        const saved = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-bs-theme', saved);
        updateThemeIcon(saved);
        
        document.getElementById('themeBtn').onclick = () => {
            const cur = document.documentElement.getAttribute('data-bs-theme');
            const next = cur === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-bs-theme', next);
            localStorage.setItem('theme', next);
            updateThemeIcon(next);
        };
    }
    
    function updateThemeIcon(theme) {
        const btn = document.getElementById('themeBtn');
        btn.innerHTML = theme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
    }

</script>
</body>
</html>

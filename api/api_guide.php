<?php
// api_guide.php - Jinsi ya Kutumia API
require_once 'config.php';

if(!isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="<?php echo getLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Mwongozo wa API</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* =============================================
           API GUIDE CUSTOM STYLES
           ============================================= */
        .api-guide-hero {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: white;
            padding: 40px;
            border-radius: 16px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .api-guide-hero::before {
            content: '</>';
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 80px;
            opacity: 0.1;
            font-family: monospace;
        }
        .api-guide-hero h1 {
            font-size: 32px;
            color: white;
            margin-bottom: 8px;
        }
        .api-guide-hero h1 i {
            color: #0d9488;
        }
        .api-guide-hero p {
            color: #94a3b8;
            font-size: 16px;
        }
        .api-guide-hero .badges {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 16px;
        }
        .api-guide-hero .badges span {
            background: rgba(255,255,255,0.1);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            color: #e2e8f0;
        }
        .api-guide-hero .badges span i {
            color: #0d9488;
            margin-right: 6px;
        }
        
        .guide-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border-left: 4px solid #0d9488;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .guide-section h3 {
            color: #1e293b;
            font-size: 20px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .guide-section h3 i {
            color: #0d9488;
        }
        .guide-section .subtitle {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 16px;
        }
        
        .code-block {
            background: #0f172a;
            color: #e2e8f0;
            padding: 16px 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
            margin: 12px 0;
            position: relative;
            line-height: 1.8;
        }
        .code-block .comment {
            color: #94a3b8;
        }
        .code-block .string {
            color: #fbbf24;
        }
        .code-block .keyword {
            color: #60a5fa;
        }
        .code-block .variable {
            color: #f472b6;
        }
        .code-block .number {
            color: #34d399;
        }
        .code-block .copy-btn {
            position: absolute;
            top: 8px;
            right: 12px;
            background: #0d9488;
            color: white;
            border: none;
            padding: 4px 14px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: 0.3s;
        }
        .code-block .copy-btn:hover {
            background: #0f766e;
        }
        
        .api-card {
            background: #f8fafc;
            padding: 16px;
            border-radius: 10px;
            margin: 10px 0;
            border: 1px solid #e2e8f0;
        }
        .api-card .method-badge {
            display: inline-block;
            padding: 2px 14px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 12px;
            margin-right: 8px;
            text-transform: uppercase;
        }
        .method-get { background: #dbeafe; color: #1e40af; }
        .method-post { background: #d1fae5; color: #065f46; }
        .method-put { background: #fef3c7; color: #92400e; }
        .method-delete { background: #fee2e2; color: #991b1b; }
        .api-card .endpoint {
            font-family: monospace;
            color: #0d9488;
            font-size: 15px;
            font-weight: 600;
        }
        .api-card .desc {
            color: #475569;
            font-size: 14px;
            margin-top: 4px;
        }
        .api-card .params {
            margin-top: 8px;
            font-size: 13px;
            background: white;
            padding: 10px 14px;
            border-radius: 6px;
        }
        .api-card .params table td {
            padding: 3px 14px 3px 0;
        }
        .api-card .params table td:first-child {
            font-family: monospace;
            color: #0d9488;
        }
        
        .badge-required {
            background: #fee2e2;
            color: #991b1b;
            padding: 1px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-optional {
            background: #f1f5f9;
            color: #64748b;
            padding: 1px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .note-box {
            padding: 14px 18px;
            border-radius: 10px;
            margin: 12px 0;
        }
        .note-box i {
            margin-right: 10px;
        }
        .note-info {
            background: #dbeafe;
            color: #1e40af;
        }
        .note-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .note-success {
            background: #d1fae5;
            color: #065f46;
        }
        .note-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .step-list {
            padding-left: 20px;
            line-height: 2.2;
        }
        .step-list li strong {
            color: #0d9488;
        }
        
        .tab-container {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            margin: 12px 0;
        }
        .tab-btn {
            padding: 8px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: 0.3s;
        }
        .tab-btn:hover {
            border-color: #0d9488;
        }
        .tab-btn.active {
            border-color: #0d9488;
            background: #0d9488;
            color: white;
        }
        .tab-content {
            display: none;
            padding: 16px;
            background: white;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            margin-top: 8px;
        }
        .tab-content.active {
            display: block;
        }
        
        .endpoint-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .quick-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .quick-links a {
            padding: 8px 20px;
            background: #f1f5f9;
            border-radius: 20px;
            text-decoration: none;
            color: #1e293b;
            font-size: 14px;
            transition: 0.3s;
        }
        .quick-links a:hover {
            background: #0d9488;
            color: white;
        }
        
        @media (max-width: 768px) {
            .api-guide-hero {
                padding: 24px;
            }
            .api-guide-hero h1 {
                font-size: 24px;
            }
            .code-block {
                font-size: 12px;
                padding: 12px 14px;
            }
            .endpoint-grid {
                grid-template-columns: 1fr;
            }
            .guide-section {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <!-- =============================================
             HERO SECTION
             ============================================= -->
        <div class="api-guide-hero">
            <h1><i class="fas fa-code"></i> Mwongozo wa API</h1>
            <p>Jinsi ya kuunganisha mifumo yako na <?php echo APP_NAME; ?> API</p>
            <div class="badges">
                <span><i class="fas fa-check-circle"></i> RESTful API</span>
                <span><i class="fas fa-lock"></i> Authentication: Bearer Token</span>
                <span><i class="fas fa-database"></i> Format: JSON</span>
                <span><i class="fas fa-clock"></i> Token Expiry: Siku 30</span>
            </div>
        </div>
        
        <!-- =============================================
             QUICK LINKS
             ============================================= -->
        <div class="quick-links">
            <a href="#basics"><i class="fas fa-info-circle"></i> Misingi</a>
            <a href="#auth"><i class="fas fa-lock"></i> Authentication</a>
            <a href="#examples"><i class="fas fa-code"></i> Mifano</a>
            <a href="#endpoints"><i class="fas fa-list"></i> Endpoints</a>
            <a href="#mobile"><i class="fas fa-mobile-alt"></i> Mobile App</a>
            <a href="#errors"><i class="fas fa-exclamation-triangle"></i> Matatizo</a>
        </div>
        
        <!-- =============================================
             SECTION 1: MISINGI
             ============================================= -->
        <div id="basics" class="guide-section">
            <h3><i class="fas fa-info-circle"></i> Misingi ya API</h3>
            
            <div class="note-box note-success">
                <i class="fas fa-check-circle"></i>
                <strong>Base URL:</strong> <code><?php echo APP_URL; ?>/api.php</code>
            </div>
            
            <p style="color: #475569; line-height: 1.8;">
                <strong>API (Application Programming Interface)</strong> inaruhusu mifumo mingine 
                (kama Mobile App, mifumo ya nje, n.k.) kuwasiliana na mfumo wako.
            </p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-top: 14px;">
                <div style="background: #f8fafc; padding: 14px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 28px;">🔐</div>
                    <strong>Authentication</strong>
                    <p style="font-size: 13px; color: #64748b; margin: 0;">Bearer Token</p>
                </div>
                <div style="background: #f8fafc; padding: 14px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 28px;">📦</div>
                    <strong>Format</strong>
                    <p style="font-size: 13px; color: #64748b; margin: 0;">JSON</p>
                </div>
                <div style="background: #f8fafc; padding: 14px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 28px;">🌐</div>
                    <strong>Methods</strong>
                    <p style="font-size: 13px; color: #64748b; margin: 0;">GET, POST, PUT, DELETE</p>
                </div>
                <div style="background: #f8fafc; padding: 14px; border-radius: 10px; text-align: center;">
                    <div style="font-size: 28px;">⏱️</div>
                    <strong>Timeout</strong>
                    <p style="font-size: 13px; color: #64748b; margin: 0;">Sekunde 30</p>
                </div>
            </div>
        </div>
        
        <!-- =============================================
             SECTION 2: AUTHENTICATION
             ============================================= -->
        <div id="auth" class="guide-section">
            <h3><i class="fas fa-lock"></i> Authentication (Jinsi ya Kuingia)</h3>
            
            <div class="subtitle">Hatua za kupata na kutumia token</div>
            
            <ol class="step-list">
                <li><strong>Hatua 1:</strong> Ingia (login) kupata token</li>
                <li><strong>Hatua 2:</strong> Tumia token kwenye kila request</li>
                <li><strong>Hatua 3:</strong> Token ina muda wa siku 30</li>
                <li><strong>Hatua 4:</strong> Fanyia upya token kabla haijaisha</li>
            </ol>
            
            <div class="code-block">
                <button class="copy-btn" onclick="copyCode(this)">Nakili</button>
                <span class="comment">// 1. Kuingia (Login) - Pata Token</span><br>
                <span class="keyword">POST</span> <?php echo APP_URL; ?>/api.php?endpoint=login<br>
                <span class="keyword">Content-Type:</span> <span class="string">application/json</span><br><br>
                {<br>
                &nbsp;&nbsp;<span class="string">"phone"</span>: <span class="string">"0712345678"</span>,<br>
                &nbsp;&nbsp;<span class="string">"password"</span>: <span class="string">"password123"</span><br>
                }
            </div>
            
            <div class="code-block">
                <span class="comment">// 2. Tumia Token kwenye Request</span><br>
                <span class="keyword">GET</span> <?php echo APP_URL; ?>/api.php?endpoint=profile<br>
                <span class="keyword">Authorization:</span> Bearer <span class="string">YOUR_TOKEN_HERE</span><br>
                <span class="keyword">Content-Type:</span> <span class="string">application/json</span>
            </div>
            
            <div class="note-box note-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Muhimu:</strong> Usishiriki token yako na mtu yeyote. Hifadhi mahali salama.
            </div>
        </div>
        
        <!-- =============================================
             SECTION 3: MIFANO YA CODE
             ============================================= -->
        <div id="examples" class="guide-section">
            <h3><i class="fas fa-code"></i> Mifano ya Code</h3>
            
            <div class="subtitle">Jinsi ya kutumia API kwa lugha tofauti</div>
            
            <div class="tab-container">
                <button class="tab-btn active" onclick="showTab('tab-curl')">cURL</button>
                <button class="tab-btn" onclick="showTab('tab-javascript')">JavaScript</button>
                <button class="tab-btn" onclick="showTab('tab-python')">Python</button>
                <button class="tab-btn" onclick="showTab('tab-php')">PHP</button>
                <button class="tab-btn" onclick="showTab('tab-android')">Android</button>
                <button class="tab-btn" onclick="showTab('tab-ios')">iOS</button>
            </div>
            
            <!-- cURL -->
            <div id="tab-curl" class="tab-content active">
                <div class="code-block">
                    <span class="comment"># 1. Kuingia (Login)</span><br>
                    curl -X POST <?php echo APP_URL; ?>/api.php?endpoint=login \<br>
                    &nbsp;&nbsp;-H <span class="string">"Content-Type: application/json"</span> \<br>
                    &nbsp;&nbsp;-d <span class="string">'{"phone":"0712345678","password":"password123"}'</span>
                    <br><br>
                    <span class="comment"># 2. Kuomba Mkopo (Request Loan)</span><br>
                    curl -X POST <?php echo APP_URL; ?>/api.php?endpoint=loans/request \<br>
                    &nbsp;&nbsp;-H <span class="string">"Authorization: Bearer YOUR_TOKEN"</span> \<br>
                    &nbsp;&nbsp;-H <span class="string">"Content-Type: application/json"</span> \<br>
                    &nbsp;&nbsp;-d <span class="string">'{"amount":100000,"term_months":6,"purpose":"Kuanzisha biashara"}'</span>
                </div>
            </div>
            
            <!-- JavaScript -->
            <div id="tab-javascript" class="tab-content">
                <div class="code-block">
                    <span class="comment">// 1. Kuingia (Login)</span><br>
                    <span class="keyword">fetch</span>(<span class="string">'<?php echo APP_URL; ?>/api.php?endpoint=login'</span>, {<br>
                    &nbsp;&nbsp;<span class="keyword">method</span>: <span class="string">'POST'</span>,<br>
                    &nbsp;&nbsp;<span class="keyword">headers</span>: { <span class="string">'Content-Type'</span>: <span class="string">'application/json'</span> },<br>
                    &nbsp;&nbsp;<span class="keyword">body</span>: <span class="variable">JSON</span>.<span class="keyword">stringify</span>({<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;phone: <span class="string">'0712345678'</span>,<br>
                    &nbsp;&nbsp;&nbsp;&nbsp;password: <span class="string">'password123'</span><br>
                    &nbsp;&nbsp;})<br>
                    })<br>
                    .<span class="keyword">then</span>(response => response.<span class="keyword">json</span>())<br>
                    .<span class="keyword">then</span>(data => {<br>
                    &nbsp;&nbsp;<span class="keyword">const</span> token = data.data.token;<br>
                    &nbsp;&nbsp;localStorage.<span class="keyword">setItem</span>(<span class="string">'api_token'</span>, token);<br>
                    });
                </div>
            </div>
            
            <!-- Python -->
            <div id="tab-python" class="tab-content">
                <div class="code-block">
                    <span class="comment"># 1. Kuingia (Login)</span><br>
                    <span class="keyword">import</span> requests<br><br>
                    url = <span class="string">"<?php echo APP_URL; ?>/api.php?endpoint=login"</span><br>
                    data = {<br>
                    &nbsp;&nbsp;<span class="string">"phone"</span>: <span class="string">"0712345678"</span>,<br>
                    &nbsp;&nbsp;<span class="string">"password"</span>: <span class="string">"password123"</span><br>
                    }<br>
                    response = requests.<span class="keyword">post</span>(url, json=data)<br>
                    token = response.<span class="keyword">json</span>()[<span class="string">'data'</span>][<span class="string">'token'</span>]<br>
                    <span class="keyword">print</span>(<span class="string">"Token:"</span>, token)
                </div>
            </div>
            
            <!-- PHP -->
            <div id="tab-php" class="tab-content">
                <div class="code-block">
                    <span class="comment">// 1. Kuingia (Login)</span><br>
                    $url = <span class="string">'<?php echo APP_URL; ?>/api.php?endpoint=login'</span>;<br>
                    $data = [<br>
                    &nbsp;&nbsp;<span class="string">'phone'</span> => <span class="string">'0712345678'</span>,<br>
                    &nbsp;&nbsp;<span class="string">'password'</span> => <span class="string">'password123'</span><br>
                    ];<br><br>
                    $ch = curl_init();<br>
                    curl_setopt($ch, CURLOPT_URL, $url);<br>
                    curl_setopt($ch, CURLOPT_POST, <span class="keyword">true</span>);<br>
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));<br>
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [<br>
                    &nbsp;&nbsp;<span class="string">'Content-Type: application/json'</span><br>
                    ]);<br>
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, <span class="keyword">true</span>);<br>
                    $response = curl_exec($ch);<br>
                    $result = json_decode($response, <span class="keyword">true</span>);<br>
                    $token = $result[<span class="string">'data'</span>][<span class="string">'token'</span>];<br>
                    <span class="keyword">echo</span> <span class="string">"Token: "</span> . $token;
                </div>
            </div>
            
            <!-- Android -->
            <div id="tab-android" class="tab-content">
                <div class="code-block">
                    <span class="comment">// 1. Kuingia (Login) - Android/Java</span><br>
                    OkHttpClient client = <span class="keyword">new</span> OkHttpClient();<br>
                    MediaType mediaType = MediaType.<span class="keyword">parse</span>(<span class="string">"application/json"</span>);<br><br>
                    JSONObject json = <span class="keyword">new</span> JSONObject();<br>
                    json.<span class="keyword">put</span>(<span class="string">"phone"</span>, <span class="string">"0712345678"</span>);<br>
                    json.<span class="keyword">put</span>(<span class="string">"password"</span>, <span class="string">"password123"</span>);<br><br>
                    RequestBody body = RequestBody.<span class="keyword">create</span>(mediaType, json.<span class="keyword">toString</span>());<br>
                    Request request = <span class="keyword">new</span> Request.Builder()<br>
                    &nbsp;&nbsp;.url(<span class="string">"<?php echo APP_URL; ?>/api.php?endpoint=login"</span>)<br>
                    &nbsp;&nbsp;.post(body)<br>
                    &nbsp;&nbsp;.addHeader(<span class="string">"Content-Type"</span>, <span class="string">"application/json"</span>)<br>
                    &nbsp;&nbsp;.build();
                </div>
            </div>
            
            <!-- iOS -->
            <div id="tab-ios" class="tab-content">
                <div class="code-block">
                    <span class="comment">// 1. Kuingia (Login) - iOS/Swift</span><br>
                    <span class="keyword">let</span> url = URL(string: <span class="string">"<?php echo APP_URL; ?>/api.php?endpoint=login"</span>)!<br>
                    <span class="keyword">var</span> request = URLRequest(url: url)<br>
                    request.httpMethod = <span class="string">"POST"</span><br>
                    request.setValue(<span class="string">"application/json"</span>, forHTTPHeaderField: <span class="string">"Content-Type"</span>)<br><br>
                    <span class="keyword">let</span> body: [String: Any] = [<br>
                    &nbsp;&nbsp;<span class="string">"phone"</span>: <span class="string">"0712345678"</span>,<br>
                    &nbsp;&nbsp;<span class="string">"password"</span>: <span class="string">"password123"</span><br>
                    ]<br>
                    request.httpBody = try? JSONSerialization.data(withJSONObject: body)
                </div>
            </div>
        </div>
        
        <!-- =============================================
             SECTION 4: ENDPOINTS
             ============================================= -->
        <div id="endpoints" class="guide-section">
            <h3><i class="fas fa-list"></i> Endpoints Muhimu</h3>
            
            <div class="endpoint-grid">
                <!-- Login -->
                <div class="api-card">
                    <span class="method-badge method-post">POST</span>
                    <span class="endpoint">/login</span>
                    <div class="desc">Kuingia - Pata API token</div>
                    <div class="params">
                        <table>
                            <tr><td>phone</td><td><span class="badge-required">Required</span></td><td>Namba ya simu</td></tr>
                            <tr><td>password</td><td><span class="badge-required">Required</span></td><td>Nenosiri</td></tr>
                        </table>
                    </div>
                </div>
                
                <!-- Profile -->
                <div class="api-card">
                    <span class="method-badge method-get">GET</span>
                    <span class="endpoint">/profile</span>
                    <div class="desc">Angalia wasifu wako</div>
                    <div class="params">
                        <span class="badge-required">Authentication Required</span>
                    </div>
                </div>
                
                <!-- Loans -->
                <div class="api-card">
                    <span class="method-badge method-get">GET</span>
                    <span class="endpoint">/loans</span>
                    <div class="desc">Angalia mikopo yako</div>
                    <div class="params">
                        <span class="badge-required">Authentication Required</span>
                    </div>
                </div>
                
                <!-- Request Loan -->
                <div class="api-card">
                    <span class="method-badge method-post">POST</span>
                    <span class="endpoint">/loans/request</span>
                    <div class="desc">Omba mkopo</div>
                    <div class="params">
                        <table>
                            <tr><td>amount</td><td><span class="badge-required">Required</span></td><td>Kiasi</td></tr>
                            <tr><td>term_months</td><td><span class="badge-required">Required</span></td><td>Muda (miezi)</td></tr>
                            <tr><td>purpose</td><td><span class="badge-optional">Optional</span></td><td>Madhumuni</td></tr>
                        </table>
                    </div>
                </div>
                
                <!-- Statistics -->
                <div class="api-card">
                    <span class="method-badge method-get">GET</span>
                    <span class="endpoint">/stats</span>
                    <div class="desc">Angalia takwimu za dashibodi</div>
                    <div class="params">
                        <span class="badge-required">Authentication Required</span>
                    </div>
                </div>
                
                <!-- M-PESA -->
                <div class="api-card">
                    <span class="method-badge method-post">POST</span>
                    <span class="endpoint">/mpesa/pay</span>
                    <div class="desc">Lipa kwa M-PESA</div>
                    <div class="params">
                        <table>
                            <tr><td>type</td><td><span class="badge-required">Required</span></td><td>contribution au loan_repayment</td></tr>
                            <tr><td>amount</td><td><span class="badge-required">Required</span></td><td>Kiasi</td></tr>
                            <tr><td>phone</td><td><span class="badge-optional">Optional</span></td><td>Namba ya M-PESA</td></tr>
                        </table>
                    </div>
                </div>
                
                <!-- Chat Rooms -->
                <div class="api-card">
                    <span class="method-badge method-get">GET</span>
                    <span class="endpoint">/chat/rooms</span>
                    <div class="desc">Angalia chat rooms zako</div>
                    <div class="params">
                        <span class="badge-required">Authentication Required</span>
                    </div>
                </div>
                
                <!-- Send Message -->
                <div class="api-card">
                    <span class="method-badge method-post">POST</span>
                    <span class="endpoint">/chat/send</span>
                    <div class="desc">Tuma ujumbe</div>
                    <div class="params">
                        <table>
                            <tr><td>room_id</td><td><span class="badge-required">Required</span></td></tr>
                            <tr><td>message</td><td><span class="badge-required">Required</span></td><td>Ujumbe</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 16px; text-align: center;">
                <a href="api_docs.php" class="btn btn-primary" style="width: auto; background: #6366f1;">
                    <i class="fas fa-book"></i> Angalia API Documentation Kamili
                </a>
            </div>
        </div>
        
        <!-- =============================================
             SECTION 5: MOBILE APP
             ============================================= -->
        <div id="mobile" class="guide-section" style="border-left-color: #8b5cf6;">
            <h3><i class="fas fa-mobile-alt" style="color: #8b5cf6;"></i> Kuunda Mobile App</h3>
            
            <div class="step-list">
                <li><strong>Hatua 1:</strong> Unda token kwenye <a href="api_token.php" style="color: #0d9488; font-weight: 600;">ukurasa wa API Token</a></li>
                <li><strong>Hatua 2:</strong> Weka token kwenye app yako (tumia secure storage)</li>
                <li><strong>Hatua 3:</strong> Tumia token kwenye kila request</li>
                <li><strong>Hatua 4:</strong> Fanyia upya token kabla haijaisha (siku 30)</li>
            </ol>
            
            <div class="note-box note-info">
                <i class="fas fa-lightbulb"></i>
                <strong>Kidokezo:</strong> Tumia encrypted storage (Android Keystore / iOS KeyChain) kuhifadhi token.
            </div>
            
            <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 16px;">
                <a href="api_token.php" class="btn btn-primary" style="width: auto; text-align: center;">
                    <i class="fas fa-key"></i> Unda Token
                </a>
                <a href="api_docs.php" class="btn btn-primary" style="width: auto; text-align: center; background: #6366f1;">
                    <i class="fas fa-file-alt"></i> Documentation
                </a>
            </div>
        </div>
        
        <!-- =============================================
             SECTION 6: MATATIZO
             ============================================= -->
        <div id="errors" class="guide-section" style="border-left-color: #ef4444;">
            <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Matatizo ya Kawaida</h3>
            
            <div class="api-card">
                <strong style="color: #ef4444;">❌ 401 Unauthorized</strong>
                <div class="desc">Token haijasahihishwa au imeisha muda. Ingia tena kupata token mpya.</div>
            </div>
            
            <div class="api-card">
                <strong style="color: #ef4444;">❌ 403 Forbidden</strong>
                <div class="desc">Huna ruhusa ya kufanya kitendo hiki. Angalia jukumu lako.</div>
            </div>
            
            <div class="api-card">
                <strong style="color: #ef4444;">❌ 404 Not Found</strong>
                <div class="desc">Endpoint haipo. Angalia spelling ya endpoint.</div>
            </div>
            
            <div class="api-card">
                <strong style="color: #f59e0b;">⚠️ Token imeisha muda</strong>
                <div class="desc">Token ina muda wa siku 30. Ingia tena au unda token mpya kwenye <a href="api_token.php" style="color: #0d9488;">ukurasa wa token</a>.</div>
            </div>
            
            <div class="note-box note-danger">
                <i class="fas fa-exclamation-circle"></i>
                <strong>Jibu:</strong> Kwa hitilafu yoyote, angalia <strong>status code</strong> kwanza. 
                Kisha angalia ujumbe wa error kwenye response.
            </div>
        </div>
        
        <!-- =============================================
             SECTION 7: VIUNGO
             ============================================= -->
        <div class="guide-section" style="border-left-color: #0d9488;">
            <h3><i class="fas fa-link"></i> Viungo Muhimu</h3>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                <a href="api_token.php" class="btn btn-primary" style="width: auto; text-align: center;">
                    <i class="fas fa-key"></i> Unda Token
                </a>
                <a href="api_docs.php" class="btn btn-primary" style="width: auto; text-align: center; background: #6366f1;">
                    <i class="fas fa-book"></i> Documentation
                </a>
                <a href="profile.php" class="btn btn-outline" style="width: auto; text-align: center;">
                    <i class="fas fa-user"></i> Wasifu Wangu
                </a>
                <a href="dashboard.php" class="btn btn-outline" style="width: auto; text-align: center;">
                    <i class="fas fa-home"></i> Dashibodi
                </a>
            </div>
        </div>
        
    </div>
    
    <!-- =============================================
         JAVASCRIPT
         ============================================= -->
    <script>
        // Copy code to clipboard
        function copyCode(btn) {
            const codeBlock = btn.parentElement;
            let code = '';
            const children = codeBlock.childNodes;
            children.forEach(node => {
                if (node.nodeType === Node.TEXT_NODE) {
                    code += node.textContent;
                } else if (node.nodeType === Node.ELEMENT_NODE) {
                    code += node.textContent + '\n';
                }
            });
            code = code.replace('Nakili', '').trim();
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(code).then(function() {
                    btn.textContent = '✓ Imenakiliwa';
                    btn.style.background = '#22c55e';
                    setTimeout(() => {
                        btn.textContent = 'Nakili';
                        btn.style.background = '#0d9488';
                    }, 2500);
                }).catch(function() {
                    fallbackCopy(code, btn);
                });
            } else {
                fallbackCopy(code, btn);
            }
        }
        
        function fallbackCopy(text, btn) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            btn.textContent = '✓ Imenakiliwa';
            btn.style.background = '#22c55e';
            setTimeout(() => {
                btn.textContent = 'Nakili';
                btn.style.background = '#0d9488';
            }, 2500);
        }
        
        // Tab functionality
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.querySelectorAll('.tab-btn').forEach(btn => {
                const btnText = btn.textContent.trim().toUpperCase();
                const tabName = tabId.replace('tab-', '').toUpperCase();
                if (btnText === tabName || btnText === tabName.replace('-', '')) {
                    btn.classList.add('active');
                }
            });
        }
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
        
        // Auto-select first tab
        document.addEventListener('DOMContentLoaded', function() {
            // Check if any tab is active, if not activate first
            if (!document.querySelector('.tab-content.active')) {
                const firstTab = document.querySelector('.tab-content');
                if (firstTab) {
                    firstTab.classList.add('active');
                }
            }
        });
    </script>
</body>
</html>

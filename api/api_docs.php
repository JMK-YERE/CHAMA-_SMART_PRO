<?php
// api_docs.php - API Documentation
require_once 'config.php';

if(!isLoggedIn()) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="<?php echo getLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - API Documentation</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .api-section {
            margin-bottom: 30px;
        }
        .api-section h3 {
            background: #f1f5f9;
            padding: 10px 16px;
            border-radius: 8px;
            border-left: 4px solid #0d9488;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .endpoint {
            background: #f8fafc;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #e2e8f0;
        }
        .endpoint .method {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 12px;
            margin-right: 10px;
            text-transform: uppercase;
        }
        .method-get { background: #dbeafe; color: #1e40af; }
        .method-post { background: #d1fae5; color: #065f46; }
        .method-put { background: #fef3c7; color: #92400e; }
        .method-delete { background: #fee2e2; color: #991b1b; }
        .endpoint .path {
            font-family: monospace;
            background: #1e293b;
            color: #0d9488;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 14px;
        }
        .endpoint .desc {
            color: #64748b;
            font-size: 14px;
            margin-top: 4px;
        }
        .endpoint .example {
            margin-top: 8px;
            background: #1e293b;
            color: #e2e8f0;
            padding: 12px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
            max-height: 150px;
        }
        .params {
            margin-top: 8px;
            padding: 8px 12px;
            background: white;
            border-radius: 6px;
            font-size: 13px;
            border: 1px solid #e2e8f0;
        }
        .params table td {
            padding: 4px 12px 4px 0;
        }
        .params table tr td:first-child {
            font-family: monospace;
            color: #0d9488;
        }
        .params table tr td:last-child {
            color: #64748b;
        }
        .badge-required {
            background: #fee2e2;
            color: #991b1b;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-optional {
            background: #f1f5f9;
            color: #64748b;
            padding: 1px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .search-box input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
        }
        .search-box input:focus {
            outline: none;
            border-color: #0d9488;
        }
        .toc {
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .toc a {
            padding: 6px 14px;
            border-radius: 20px;
            background: white;
            color: #1e293b;
            text-decoration: none;
            font-size: 13px;
            border: 1px solid #e2e8f0;
            transition: 0.3s;
        }
        .toc a:hover {
            background: #0d9488;
            color: white;
            border-color: #0d9488;
        }
        @media (max-width: 768px) {
            .endpoint {
                padding: 10px 12px;
            }
            .endpoint .example {
                font-size: 11px;
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        
        <div class="dashboard-card fade-in">
            <h2><i class="fas fa-code"></i> API Documentation</h2>
            <p style="color: #64748b; margin-bottom: 20px;">
                Mobile App API endpoints for <?php echo APP_NAME; ?> 
                <span class="badge badge-active">v<?php echo APP_VERSION; ?></span>
            </p>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Base URL:</strong> <code><?php echo APP_URL; ?>/api.php</code>
                    <br>
                    <strong>Authentication:</strong> Bearer Token (include in header: <code>Authorization: Bearer YOUR_TOKEN</code>)
                    <br>
                    <strong>Content-Type:</strong> <code>application/json</code>
                </div>
            </div>
            
            <!-- Table of Contents -->
            <div class="toc">
                <a href="#auth">Authentication</a>
                <a href="#profile">Profile</a>
                <a href="#loans">Loans</a>
                <a href="#contributions">Contributions</a>
                <a href="#repayments">Repayments</a>
                <a href="#mpesa">M-PESA</a>
                <a href="#chat">Chat</a>
                <a href="#meetings">Meetings</a>
                <a href="#reports">Reports</a>
                <a href="#stats">Statistics</a>
                <a href="#settings">Settings</a>
            </div>
            
            <!-- ========================================== -->
            <!-- AUTHENTICATION -->
            <!-- ========================================== -->
            <div id="auth" class="api-section">
                <h3><i class="fas fa-lock"></i> Authentication</h3>
                
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="path">/login</span>
                    <div class="desc">Login user and get API token</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>phone</td><td>string</td><td><span class="badge-required">Required</span></td><td>Namba ya simu</td></tr>
                            <tr><td>password</td><td>string</td><td><span class="badge-required">Required</span></td><td>Nenosiri</td></tr>
                            <tr><td>device_name</td><td>string</td><td><span class="badge-optional">Optional</span></td><td>Jina la kifaa</td></tr>
                            <tr><td>platform</td><td>string</td><td><span class="badge-optional">Optional</span></td><td>mobile, desktop, web</td></tr>
                        </table>
                    </div>
                    <div class="example">
// Example Request
POST /api.php?endpoint=login
{
    "phone": "0712345678",
    "password": "password123",
    "device_name": "iPhone 15",
    "platform": "mobile"
}

// Example Response
{
    "status": 200,
    "success": true,
    "data": {
        "token": "abc123def456...",
        "user": {
            "id": 1,
            "name": "Juma Mwenyekiti",
            "phone": "0712345678",
            "role": "mwenyekiti",
            "savings": 250000
        }
    }
}
                    </div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="path">/register</span>
                    <div class="desc">Register new member</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>first_name</td><td>string</td><td><span class="badge-required">Required</span></td><td>Jina la kwanza</td></tr>
                            <tr><td>last_name</td><td>string</td><td><span class="badge-required">Required</span></td><td>Jina la mwisho</td></tr>
                            <tr><td>phone</td><td>string</td><td><span class="badge-required">Required</span></td><td>Namba ya simu</td></tr>
                            <tr><td>email</td><td>string</td><td><span class="badge-optional">Optional</span></td><td>Barua pepe</td></tr>
                            <tr><td>nida</td><td>string</td><td><span class="badge-optional">Optional</span></td><td>Namba ya NIDA</td></tr>
                            <tr><td>monthly_income</td><td>number</td><td><span class="badge-optional">Optional</span></td><td>Mapato ya mwezi</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- ========================================== -->
            <!-- PROFILE -->
            <!-- ========================================== -->
            <div id="profile" class="api-section">
                <h3><i class="fas fa-user"></i> Profile</h3>
                
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="path">/profile</span>
                    <div class="desc">Get current user profile</div>
                    <div class="params">
                        <strong>Authentication Required:</strong> Bearer Token
                    </div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="path">/profile/update</span>
                    <div class="desc">Update user profile</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>email</td><td>string</td><td><span class="badge-optional">Optional</span></td></tr>
                            <tr><td>monthly_income</td><td>number</td><td><span class="badge-optional">Optional</span></td></tr>
                            <tr><td>mpesa_phone</td><td>string</td><td><span class="badge-optional">Optional</span></td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="path">/profile/password</span>
                    <div class="desc">Change password</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>current</td><td>string</td><td><span class="badge-required">Required</span></td><td>Nenosiri la sasa</td></tr>
                            <tr><td>new</td><td>string</td><td><span class="badge-required">Required</span></td><td>Nenosiri jipya (min 6)</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- ========================================== -->
            <!-- LOANS -->
            <!-- ========================================== -->
            <div id="loans" class="api-section">
                <h3><i class="fas fa-hand-holding-usd"></i> Loans</h3>
                
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="path">/loans</span>
                    <div class="desc">Get user's loans</div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="path">/loans/request</span>
                    <div class="desc">Request a loan</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>amount</td><td>number</td><td><span class="badge-required">Required</span></td><td>Kiasi cha mkopo</td></tr>
                            <tr><td>term_months</td><td>number</td><td><span class="badge-required">Required</span></td><td>Muda (miezi)</td></tr>
                            <tr><td>purpose</td><td>string</td><td><span class="badge-optional">Optional</span></td><td>Madhumuni</td></tr>
                            <tr><td>interest_rate</td><td>number</td><td><span class="badge-optional">Optional</span></td><td>Riba (default 5%)</td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="path">/loans/approve</span>
                    <div class="desc">Approve a loan (Chairperson/Treasurer/Auditor only)</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>loan_id</td><td>number</td><td><span class="badge-required">Required</span></td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="path">/loans/reject</span>
                    <div class="desc">Reject a loan (Chairperson/Treasurer/Auditor only)</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>loan_id</td><td>number</td><td><span class="badge-required">Required</span></td></tr>
                            <tr><td>reason</td><td>string</td><td><span class="badge-optional">Optional</span></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- ========================================== -->
            <!-- CONTRIBUTIONS -->
            <!-- ========================================== -->
            <div id="contributions" class="api-section">
                <h3><i class="fas fa-coins"></i> Contributions</h3>
                
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="path">/contributions</span>
                    <div class="desc">Get user's contributions</div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="path">/contributions/add</span>
                    <div class="desc">Add contribution (Treasurer/Chairperson only)</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>member_id</td><td>number</td><td><span class="badge-required">Required</span></td></tr>
                            <tr><td>amount</td><td>number</td><td><span class="badge-required">Required</span></td></tr>
                            <tr><td>description</td><td>string</td><td><span class="badge-optional">Optional</span></td></tr>
                            <tr><td>payment_method</td><td>string</td><td><span class="badge-optional">Optional</span></td><td>cash, bank, mobile</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- ========================================== -->
            <!-- REPAYMENTS -->
            <!-- ========================================== -->
            <div id="repayments" class="api-section">
                <h3><i class="fas fa-hand-holding-heart"></i> Repayments</h3>
                
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="path">/repayments</span>
                    <div class="desc">Get user's loan repayments</div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="path">/repayments/add</span>
                    <div class="desc">Record a repayment</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>loan_id</td><td>number</td><td><span class="badge-required">Required</span></td></tr>
                            <tr><td>amount</td><td>number</td><td><span class="badge-required">Required</span></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- ========================================== -->
            <!-- M-PESA -->
            <!-- ========================================== -->
            <div id="mpesa" class="api-section">
                <h3><i class="fas fa-mobile-alt"></i> M-PESA</h3>
                
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="path">/mpesa/pay</span>
                    <div class="desc">Initiate M-PESA payment</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>type</td><td>string</td><td><span class="badge-required">Required</span></td><td>contribution or loan_repayment</td></tr>
                            <tr><td>amount</td><td>number</td><td><span class="badge-required">Required</span></td></tr>
                            <tr><td>phone</td><td>string</td><td><span class="badge-optional">Optional</span></td><td>Namba ya M-PESA</td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="path">/mpesa/status?id=TRANSACTION_ID</span>
                    <div class="desc">Check M-PESA transaction status</div>
                </div>
            </div>
            
            <!-- ========================================== -->
            <!-- CHAT -->
            <!-- ========================================== -->
            <div id="chat" class="api-section">
                <h3><i class="fas fa-comment-dots"></i> Chat</h3>
                
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="path">/chat/rooms</span>
                    <div class="desc">Get user's chat rooms</div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="path">/chat/messages?room_id=ROOM_ID</span>
                    <div class="desc">Get messages from a room</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>room_id</td><td>number</td><td><span class="badge-required">Required</span></td></tr>
                            <tr><td>limit</td><td>number</td><td><span class="badge-optional">Optional</span></td><td>Default: 50</td></tr>
                            <tr><td>offset</td><td>number</td><td><span class="badge-optional">Optional</span></td><td>Default: 0</td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="path">/chat/send</span>
                    <div class="desc">Send a message</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>room_id</td><td>number</td><td><span class="badge-required">Required</span></td></tr>
                            <tr><td>message</td><td>string</td><td><span class="badge-required">Required</span></td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="path">/chat/create</span>
                    <div class="desc">Create a new private chat</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>member_id</td><td>number</td><td><span class="badge-required">Required</span></td></tr>
                            <tr><td>message</td><td>string</td><td><span class="badge-optional">Optional</span></td><td>Ujumbe wa kwanza</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- ========================================== -->
            <!-- MEETINGS -->
            <!-- ========================================== -->
            <div id="meetings" class="api-section">
                <h3><i class="fas fa-video"></i> Meetings</h3>
                
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="path">/meetings</span>
                    <div class="desc">Get upcoming meetings</div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="path">/meetings/join</span>
                    <div class="desc">Confirm attendance for a meeting</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>meeting_id</td><td>number</td><td><span class="badge-required">Required</span></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- ========================================== -->
            <!-- REPORTS -->
            <!-- ========================================== -->
            <div id="reports" class="api-section">
                <h3><i class="fas fa-file-alt"></i> Reports</h3>
                
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="path">/reports/members</span>
                    <div class="desc">Get all members (Leaders only)</div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="path">/reports/loans</span>
                    <div class="desc">Get all loans (Leaders only)</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>status</td><td>string</td><td><span class="badge-optional">Optional</span></td><td>Filter by status</td></tr>
                        </table>
                    </div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="path">/reports/contributions</span>
                    <div class="desc">Get all contributions (Leaders only)</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>member_id</td><td>number</td><td><span class="badge-optional">Optional</span></td><td>Filter by member</td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- ========================================== -->
            <!-- STATISTICS -->
            <!-- ========================================== -->
            <div id="stats" class="api-section">
                <h3><i class="fas fa-chart-line"></i> Statistics</h3>
                
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="path">/stats</span>
                    <div class="desc">Get dashboard statistics</div>
                </div>
            </div>
            
            <!-- ========================================== -->
            <!-- SETTINGS -->
            <!-- ========================================== -->
            <div id="settings" class="api-section">
                <h3><i class="fas fa-sliders-h"></i> Settings</h3>
                
                <div class="endpoint">
                    <span class="method method-get">GET</span>
                    <span class="path">/settings</span>
                    <div class="desc">Get loan settings (Chairperson/Treasurer only)</div>
                </div>
                
                <div class="endpoint">
                    <span class="method method-post">POST</span>
                    <span class="path">/settings/update</span>
                    <div class="desc">Update loan settings (Chairperson/Treasurer only)</div>
                    <div class="params">
                        <strong>Parameters:</strong>
                        <table>
                            <tr><td>max_loan_percentage</td><td>number</td><td><span class="badge-optional">Optional</span></td></tr>
                            <tr><td>max_loan_income_percentage</td><td>number</td><td><span class="badge-optional">Optional</span></td></tr>
                            <tr><td>default_interest_rate</td><td>number</td><td><span class="badge-optional">Optional</span></td></tr>
                            <tr><td>min_contributions_required</td><td>number</td><td><span class="badge-optional">Optional</span></td></tr>
                            <tr><td>contribution_period_months</td><td>number</td><td><span class="badge-optional">Optional</span></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- ========================================== -->
            <!-- ERROR CODES -->
            <!-- ========================================== -->
            <div class="api-section">
                <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Error Codes</h3>
                
                <div class="endpoint">
                    <div class="desc"><strong>400:</strong> Bad Request - Missing or invalid parameters</div>
                    <div class="desc"><strong>401:</strong> Unauthorized - Invalid or missing token</div>
                    <div class="desc"><strong>403:</strong> Forbidden - Insufficient permissions</div>
                    <div class="desc"><strong>404:</strong> Not Found - Endpoint not found</div>
                    <div class="desc"><strong>405:</strong> Method Not Allowed - Wrong HTTP method</div>
                    <div class="desc"><strong>500:</strong> Internal Server Error</div>
                </div>
                
                <div class="endpoint">
                    <div class="desc"><strong>Example Error Response:</strong></div>
                    <div class="example">
{
    "status": 401,
    "success": false,
    "error": "Unauthorized. Please login first.",
    "timestamp": "2025-06-21 10:30:00"
}
                    </div>
                </div>
            </div>
            
            <!-- ========================================== -->
            <!-- TIPS -->
            <!-- ========================================== -->
            <div class="api-section">
                <h3><i class="fas fa-lightbulb" style="color: #f59e0b;"></i> Tips for Developers</h3>
                
                <div style="background: #f1f5f9; padding: 16px; border-radius: 12px;">
                    <ol style="padding-left: 20px; line-height: 2;">
                        <li><strong>Always use HTTPS</strong> in production for security</li>
                        <li><strong>Store token securely</strong> (use encrypted storage on mobile)</li>
                        <li><strong>Refresh token</strong> before it expires (30 days)</li>
                        <li><strong>Handle errors gracefully</strong> - check the status code first</li>
                        <li><strong>Use pagination</strong> for large data sets (limit/offset)</li>
                        <li><strong>Log API calls</strong> on your side for debugging</li>
                    </ol>
                </div>
            </div>
            
            <div style="margin-top: 20px; text-align: center;">
                <a href="dashboard.php" class="btn btn-outline" style="width: auto;">
                    <i class="fas fa-arrow-left"></i> Rudi Dashibodi
                </a>
                <a href="api_token.php" class="btn btn-primary" style="width: auto;">
                    <i class="fas fa-key"></i> Unda Token
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // Search functionality
        document.querySelector('.search-box input')?.addEventListener('keyup', function() {
            const search = this.value.toLowerCase();
            document.querySelectorAll('.endpoint').forEach(el => {
                const text = el.textContent.toLowerCase();
                el.style.display = text.includes(search) ? '' : 'none';
            });
        });
        
        // Smooth scrolling for TOC links
        document.querySelectorAll('.toc a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if(target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>

<?php
// includes/footer.php
?>
    </div> <!-- End of container -->
    
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-grid">
                <!-- About -->
                <div class="footer-col">
                    <h4><i class="fas fa-hand-holding-usd"></i> <?php echo APP_NAME; ?></h4>
                    <p>Mfumo wa Kuwekeza na Kukopa kwa Vikundi vya Tanzania.</p>
                    <p style="margin-top: 8px; font-size: 13px; color: #94a3b8;">
                        Toleo <?php echo APP_VERSION; ?>
                    </p>
                </div>
                
                <!-- Quick Links -->
                <div class="footer-col">
                    <h4>Viungo Haraka</h4>
                    <ul>
                        <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashibodi</a></li>
                        <li><a href="chat.php"><i class="fas fa-comment-dots"></i> Chat</a></li>
                        <li><a href="mpesa.php"><i class="fas fa-mobile-alt"></i> M-PESA</a></li>
                        <li><a href="reports.php"><i class="fas fa-file-pdf"></i> Ripoti</a></li>
                    </ul>
                </div>
                
                <!-- Support -->
                <div class="footer-col">
                    <h4>Mawasiliano</h4>
                    <ul>
                        <li><i class="fas fa-phone"></i> <?php echo APP_PHONE; ?></li>
                        <li><i class="fas fa-envelope"></i> <?php echo APP_EMAIL; ?></li>
                        <li><i class="fas fa-globe"></i> <?php echo APP_URL; ?></li>
                    </ul>
                </div>
                
                <!-- Language & Social -->
                <div class="footer-col">
                    <h4>Lugha</h4>
                    <div class="footer-languages">
                        <a href="language.php?lang=sw" class="<?php echo getLanguage() === 'sw' ? 'active' : ''; ?>">
                            <i class="fas fa-flag"></i> Kiswahili
                        </a>
                        <a href="language.php?lang=en" class="<?php echo getLanguage() === 'en' ? 'active' : ''; ?>">
                            <i class="fas fa-flag"></i> English
                        </a>
                    </div>
                    
                    <div class="footer-social" style="margin-top: 12px;">
                        <a href="#" target="_blank"><i class="fab fa-facebook"></i></a>
                        <a href="#" target="_blank"><i class="fab fa-whatsapp"></i></a>
                        <a href="#" target="_blank"><i class="fab fa-instagram"></i></a>
                        <a href="#" target="_blank"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Haki zote zimehifadhiwa.</p>
                <p style="margin-top: 4px; font-size: 12px; color: #94a3b8;">
                    <i class="fas fa-heart" style="color: #ef4444;"></i> 
                    Imetengenezwa kwa Upendo Tanzania
                </p>
            </div>
        </div>
    </footer>
    
    <style>
        .footer {
            background: #0f172a;
            color: #e2e8f0;
            padding: 40px 0 20px;
            margin-top: 40px;
            border-radius: 24px 24px 0 0;
        }
        
        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 24px;
        }
        
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .footer-col h4 {
            color: white;
            font-size: 16px;
            margin-bottom: 14px;
            position: relative;
        }
        
        .footer-col h4::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -6px;
            width: 40px;
            height: 2px;
            background: #0d9488;
        }
        
        .footer-col p {
            color: #94a3b8;
            line-height: 1.8;
            font-size: 14px;
        }
        
        .footer-col ul {
            list-style: none;
            padding: 0;
        }
        
        .footer-col ul li {
            padding: 6px 0;
            color: #94a3b8;
            font-size: 14px;
        }
        
        .footer-col ul li a {
            color: #94a3b8;
            text-decoration: none;
            transition: 0.3s;
        }
        
        .footer-col ul li a:hover {
            color: #0d9488;
            padding-left: 8px;
        }
        
        .footer-col ul li i {
            width: 20px;
            color: #0d9488;
        }
        
        .footer-languages a {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            text-decoration: none;
            color: #94a3b8;
            background: #1e293b;
            margin: 4px 6px 4px 0;
            font-size: 13px;
            transition: 0.3s;
        }
        
        .footer-languages a:hover {
            background: #0d9488;
            color: white;
        }
        
        .footer-languages a.active {
            background: #0d9488;
            color: white;
        }
        
        .footer-social a {
            display: inline-block;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #1e293b;
            color: #94a3b8;
            text-align: center;
            line-height: 40px;
            margin-right: 8px;
            transition: 0.3s;
        }
        
        .footer-social a:hover {
            background: #0d9488;
            color: white;
            transform: translateY(-3px);
        }
        
        .footer-bottom {
            border-top: 1px solid #1e293b;
            padding-top: 20px;
            text-align: center;
            color: #94a3b8;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .footer-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .footer-col h4::after {
                left: 50%;
                transform: translateX(-50%);
            }
            
            .footer-col ul li i {
                width: auto;
                margin-right: 6px;
            }
            
            .footer-languages a {
                margin: 4px;
            }
        }
    </style>
</body>
</html>

</main>

    <footer class="public-footer">
        <div class="container">
            <div class="footer-content">
                <p class="footer-brand">
                    <strong>Vitrine Independente</strong> 
                    <span class="powered-by">powered by Naipe da Gringa</span>
                </p>
                
                <p class="footer-dev">
                    Confeccionado por Gustavo A. Félix
                </p>

                <p class="footer-info">
                    &copy; <?php echo date('Y'); ?> &middot; Todos os direitos reservados.
                </p>
            </div>
        </div>
    </footer>
    
    <?php
    // Botão WhatsApp Flutuante
    $whatsapp_float_enabled = getSetting($pdo, 'whatsapp_float_enabled', '1');
    $whatsapp_float_number = getSetting($pdo, 'whatsapp_float_number', '');
    $whatsapp_float_message = getSetting($pdo, 'whatsapp_float_message', 'Olá! Preciso de ajuda.');
    
    if ($whatsapp_float_enabled == '1' && !empty($whatsapp_float_number)):
        $whatsapp_number_clean = preg_replace('/[^0-9]/', '', $whatsapp_float_number);
        $whatsapp_link = 'https://wa.me/' . $whatsapp_number_clean . '?text=' . urlencode($whatsapp_float_message);
    ?>
    <span id="alertWapp">1</span>
    <div id="msgTooltip">Precisa de ajuda?</div>
    
    <div id="whatsappWidget">
        <img src="https://zeph.com.br/wp-content/uploads/2025/12/zap.svg" alt="WhatsApp" id="zapIcon" />
    </div>
    
    <script>
        function showTooltip() {
            const tooltip = document.getElementById("msgTooltip");
            tooltip.style.visibility = "visible";
            tooltip.style.transform = "translateX(0)";
            tooltip.style.opacity = "1";
        }
    
        function hideTooltip() {
            const tooltip = document.getElementById("msgTooltip");
            tooltip.style.transform = "translateX(100%)";
            tooltip.style.opacity = "0";
            setTimeout(() => tooltip.style.visibility = "hidden", 500);
        }
    
        function showAlert() {
            document.getElementById("alertWapp").style.visibility = "visible";
        }
    
        setTimeout(showTooltip, 5000);
        setTimeout(hideTooltip, 15000);
    
        setInterval(() => {
            showTooltip();
            setTimeout(hideTooltip, 10000);
        }, 25000);
    
        setTimeout(showAlert, 15000);
    
        document.getElementById("msgTooltip").onclick = hideTooltip;
    </script>
    
    <script>
        const wappLink = "<?php echo htmlspecialchars($whatsapp_link); ?>";
    
        document.getElementById("whatsappWidget").onclick = () => {
            window.open(wappLink, "_blank");
        };
    
        document.getElementById("msgTooltip").onclick = () => {
            window.open(wappLink, "_blank");
        };
    </script>
    <?php endif; ?>

    <style>
        /* --- Estilos do Rodapé --- */
        .public-footer {
            background: #1F1F1F;
            border-top: 1px solid #2A2A2A;
            padding: 2.5rem 0;
            font-family: sans-serif; /* Garante fonte limpa caso não herde */
        }

        .footer-content {
            text-align: center;
            color: #B3B3B3;
            display: flex;
            flex-direction: column;
            gap: 8px; /* Espaçamento entre as linhas */
        }

        .footer-brand {
            font-size: 1.1rem;
            color: #fff;
            margin: 0;
        }

        .footer-brand .powered-by {
            font-size: 0.85em;
            color: #888;
            font-weight: 300;
            font-style: italic;
            margin-left: 5px;
        }

        .footer-dev {
            font-size: 0.9rem;
            color: #B3B3B3;
            margin: 0;
        }

        .footer-info {
            font-size: 0.75rem;
            color: #666666;
            margin: 0;
            margin-top: 10px; /* Separa um pouco a info legal */
            border-top: 1px solid #2A2A2A;
            padding-top: 10px;
            display: inline-block;
        }

        /* --- Estilos do WhatsApp --- */
        #whatsappWidget {
            position: fixed;
            right: 16px;
            bottom: 20px;
            z-index: 10;
            cursor: pointer;
            transition: transform 0.3s ease;
        }
        
        #whatsappWidget:hover {
            transform: scale(1.05);
        }
    
        #whatsappWidget img {
            width: 60px; 
            height: 60px;
            display: block;
        }
    
        #alertWapp {
            position: fixed;
            right: 20px;
            bottom: 70px;
            width: 17px;
            height: 17px;
            z-index: 12;
            background: red;
            color: #fff;
            font-size: 11px;
            text-align: center;
            border-radius: 50%;
            font-weight: bold;
            line-height: 17px;
            visibility: hidden;
        }
    
        #msgTooltip {
            position: fixed;
            right: 95px;
            width: 72px;
            bottom: 35px;
            background: #fff;
            padding: 4px 6px;
            font-size: 12px;
            line-height: 1.1em;
            border-radius: 10px;
            border: 1px solid #e2e2e2;
            box-shadow: 2px 2px 3px #99999990;
            font-family: 'Space Grotesk', sans-serif;
            transform: translateX(100%);
            opacity: 0;
            visibility: hidden;
            transition: transform 0.5s, opacity 0.5s;
            z-index: 12;
            text-wrap: balance;
            cursor: pointer;
            color: #333; /* Adicionado para garantir leitura */
        }
    
        #msgTooltip::after {
            content: '';
            position: absolute;
            right: -7px; 
            bottom: 12px;
            width: 0;
            height: 0;
            border-top: 7px solid transparent;
            border-bottom: 7px solid transparent;
            border-left: 7px solid #fff;
            z-index: 12;
        }
    </style>
</body>
</html>
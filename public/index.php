<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../auth.php';
requireLogin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor de precios ML - Dashboard</title>
    <style>
        * {
            margin:  0;
            padding: 0;
            box-sizing:  border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background:  linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .user-info {
            text-align: right;
            margin-bottom: 15px;
        }

        . user-info span, .user-info a {
            color: white;
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius:  8px;
            display: inline-block;
            text-decoration: none;
            transition: all 0.3s;
        }

        .user-info a:hover {
            background: rgba(255,255,255,0.3);
        }

        header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow:  0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }

        h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 1.1em;
        }

        .stats-grid {
            display: grid;
            grid-template-columns:  repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius:  15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        . stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .stat-label {
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        . stat-value {
            color: #333;
            font-size: 2.5em;
            font-weight: bold;
        }

        .stat-icon {
            font-size: 3em;
            opacity: 0.2;
            float: right;
        }

        .controls {
            background: white;
            padding: 25px;
            border-radius:  15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .control-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        input[type="text"], input[type="number"], input[type="date"], select {
            flex: 1;
            min-width: 200px;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 0.95em;
            transition: border-color 0.3s;
        }

        input: focus, select:focus {
            outline: none;
            border-color: #667eea;
        }

        button {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        . btn-primary {
            background:  linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .btn-success {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.4);
        }

        #message-container {
            margin-bottom: 20px;
        }

        .success, .error {
            padding: 15px 20px;
            border-radius:  8px;
            margin-bottom:  15px;
            animation: slideIn 0.3s ease;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        @keyframes slideIn {
            from {
                transform: translateX(-100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .products-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-title {
            font-size: 1.8em;
            color: #333;
        }

        #loading {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 1.2em;
        }

        . spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
            letter-spacing: 0.5px;
        }

        td {
            padding:  15px;
            border-bottom:  1px solid #f0f0f0;
        }

        tbody tr {
            transition: background-color 0.2s ease;
        }

        tbody tr:hover {
            background-color: #f8f9ff;
        }

        .price {
            font-weight: bold;
            color: #4CAF50;
            font-size: 1.1em;
        }

        . reference {
            color: #666;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }

        .view-history {
            color: #667eea;
            cursor: pointer;
            text-decoration: underline;
            transition: color 0.2s;
        }

        .view-history:hover {
            color:  #764ba2;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height:  100%;
            background-color: rgba(0,0,0,0.6);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        . modal-content {
            background-color: white;
            margin:  5% auto;
            padding: 30px;
            border-radius:  15px;
            width:  80%;
            max-width: 900px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s;
        }

        . close:hover {
            color:  #000;
        }

        footer {
            text-align: center;
            padding: 20px;
            color: white;
            margin-top: 30px;
            opacity: 0.8;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-item label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
            font-size: 0.9em;
        }

        @media (max-width: 768px) {
            h1 {
                font-size: 1.8em;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .control-group {
                flex-direction: column;
            }

            input[type="text"] {
                min-width: 100%;
            }

            table {
                font-size: 0.9em;
            }

            th, td {
                padding: 10px 5px;
            }

            .modal-content {
                width: 95%;
                margin: 10% auto;
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="user-info">
            <span>👤 <?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="/logout.php">🚪 Cerrar Sesión</a>
        </div>

        <header>
            <h1>🌿 Monitor de precios ML</h1>
            <p class="subtitle">Monitorización inteligente de precios en tiempo real</p>
        </header>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-label">Total Productos</div>
                <div class="stat-value" id="total-products">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-label">Productos Activos</div>
                <div class="stat-value" id="active-products">0</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-label">Precio Promedio</div>
                <div class="stat-value" id="avg-price">0.00€</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🕐</div>
                <div class="stat-label">Última Actualización</div>
                <div class="stat-value" id="last-update" style="font-size: 1. 2em;">--:--</div>
            </div>
        </div>

        <div class="controls">
            <h3 style="margin-bottom: 15px; color: #333;">⚙️ Acciones</h3>
            <div id="message-container"></div>
            
            <div class="control-group">
                <input type="text" 
                       id="product-url" 
                       placeholder="Pega aquí la URL de un producto de GreenICE... ">
                <button class="btn-primary" onclick="addProduct()">➕ Añadir Producto</button>
                <button class="btn-success" onclick="startFullScrape()">🔄 Escanear Catálogo Completo</button>
                <button class="btn-secondary" onclick="refreshProducts()">↻ Refrescar</button>
            </div>
        </div>

        <div class="products-section">
            <div class="section-header">
                <h2 class="section-title">📊 Productos Monitorizados</h2>
            </div>

            <!-- FILTROS Y BÚSQUEDA -->
            <div style="background: #f8f9ff; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                <div class="filter-grid">
                    
                    <!-- Búsqueda por nombre -->
                    <div class="filter-item">
                        <label>🔍 Buscar producto</label>
                        <input type="text" 
                               id="search-input" 
                               placeholder="Nombre, referencia..." 
                               onkeyup="filterProducts()">
                    </div>

                    <!-- Filtro por precio mínimo -->
                    <div class="filter-item">
                        <label>💰 Precio mín.</label>
                        <input type="number" 
                               id="price-min" 
                               placeholder="0.00" 
                               step="0.01"
                               onchange="filterProducts()">
                    </div>

                    <!-- Filtro por precio máximo -->
                    <div class="filter-item">
                        <label>💰 Precio máx.</label>
                        <input type="number" 
                               id="price-max" 
                               placeholder="999.99" 
                               step="0.01"
                               onchange="filterProducts()">
                    </div>

                    <!-- ✅ NUEVO: Filtro solo productos con cambios -->
                    <div class="filter-item">
                        <label>📊 Mostrar</label>
                        <select id="filter-changes" onchange="filterProducts()">
                            <option value="all">Todos los productos</option>
                            <option value="with-changes">Solo con cambios</option>
                            <option value="increases">Solo subidas</option>
                            <option value="decreases">Solo bajadas</option>
                        </select>
                    </div>

                    <!-- ✅ NUEVO: Filtro por fecha de cambio -->
                    <div class="filter-item">
                        <label>📅 Cambios desde</label>
                        <input type="date" 
                               id="change-date-from" 
                               onchange="filterProducts()">
                    </div>

                    <!-- ✅ NUEVO: Filtro por fecha de cambio hasta -->
                    <div class="filter-item">
                        <label>📅 Cambios hasta</label>
                        <input type="date" 
                               id="change-date-to" 
                               onchange="filterProducts()">
                    </div>

                    <!-- Ordenar por -->
                    <div class="filter-item">
                        <label>📊 Ordenar por</label>
                        <select id="sort-by" onchange="sortProducts()">
                            <option value="id-asc">ID (menor a mayor)</option>
                            <option value="id-desc">ID (mayor a menor)</option>
                            <option value="name-asc">Nombre (A-Z)</option>
                            <option value="name-desc">Nombre (Z-A)</option>
                            <option value="price-asc">Precio (menor a mayor)</option>
                            <option value="price-desc">Precio (mayor a menor)</option>
                            <option value="date-desc">Más recientes</option>
                            <option value="change-desc">⬇️ Mayor bajada</option>
                            <option value="change-asc">⬆️ Mayor subida</option>
                            <option value="change-date-desc">🕐 Último cambio (reciente)</option>
                            <option value="change-date-asc">🕐 Último cambio (antiguo)</option>
                        </select>
                    </div>

                    <!-- Botón limpiar filtros -->
                    <div class="filter-item">
                        <label>&nbsp;</label>
                        <button class="btn-secondary" onclick="clearFilters()" style="width: 100%; margin:  0;">
                            🗑️ Limpiar filtros
                        </button>
                    </div>
                </div>

                <!-- Contador de resultados -->
                <div id="filter-results" style="margin-top: 15px; color: #666; font-size:  0.9em;"></div>
            </div>

            <div id="loading">
                <div class="spinner"></div>
                Cargando productos...
            </div>

            <table id="products-table" style="display: none;">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Producto</th>
                        <th>Referencia</th>
                        <th>Precio Actual</th>
                        <th>Precio Anterior</th>
                        <th>Cambio</th>
                        <th>Fecha Cambio</th>
                        <th>Última Act.</th>
                        <th>Historial</th>
                    </tr>
                </thead>
                <tbody id="products-tbody">
                </tbody>
            </table>
        </div>

        <div id="history-modal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeHistoryModal()">&times;</span>
                <div id="history-content"></div>
            </div>
        </div>

        <footer>
            <p>Monitor de precios ML &copy; 2025</p>
        </footer>
    </div>

    <script>
        const API_BASE = '/api/index.php';
        
        // Variables globales
        let allProducts = [];
        let filteredProducts = [];
        let currentPage = 1;
        let productsPerPage = 50;
        
        window.onload = () => {
            loadStats();
            loadProducts();
            setInterval(loadStats, 60000);
        };
        
        function showMessage(message, type = 'success') {
            const container = document.getElementById('message-container');
            container.innerHTML = `<div class="${type}">${message}</div>`;
            setTimeout(() => container.innerHTML = '', 5000);
        }
        
        async function loadStats() {
            try {
                const response = await fetch(`${API_BASE}?endpoint=products/stats`);
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('total-products').textContent = data.data.total_products || 0;
                    document. getElementById('active-products').textContent = data.data.active_products || 0;
                    document.getElementById('avg-price').textContent = parseFloat(data.data.avg_price || 0).toFixed(2) + '€';
                    document.getElementById('last-update').textContent = new Date().toLocaleTimeString('es-ES');
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }
        
        async function loadProducts() {
            const loading = document.getElementById('loading');
            const table = document.getElementById('products-table');
            
            loading.style.display = 'block';
            table.style.display = 'none';
            
            try {
                const response = await fetch(`${API_BASE}?endpoint=products&limit=10000`);
                const data = await response.json();
                
                if (data.success && data.data) {
                    allProducts = data.data;
                    filteredProducts = [... allProducts];
                    
                    currentPage = 1;
                    renderProductsWithPagination();
                    updateFilterResults();
                    table.style.display = 'table';
                }
            } catch (error) {
                console.error('Error loading products:', error);
                showMessage('Error al cargar productos', 'error');
            } finally {
                loading.style.display = 'none';
            }
        }
        
        function renderProductsWithPagination() {
            const startIndex = (currentPage - 1) * productsPerPage;
            const endIndex = startIndex + productsPerPage;
            const productsToShow = filteredProducts. slice(startIndex, endIndex);
            
            renderProducts(productsToShow);
            renderPagination();
        }
        
        function renderProducts(products) {
            const tbody = document.getElementById('products-tbody');
            tbody.innerHTML = '';
            
            if (products.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 40px; color: #999;">No se encontraron productos con los filtros aplicados</td></tr>';
                return;
            }
            
            products.forEach(product => {
                const currentPrice = parseFloat(product.current_price);
                const previousPrice = product.previous_price ?  parseFloat(product.previous_price) : null;
                
                let priceChange = 0;
                let percentageChange = 0;
                
                if (product.price_change !== null && product.price_change !== undefined) {
                    priceChange = Number(product.price_change);
                }
                
                if (product.percentage_change !== null && product.percentage_change !== undefined) {
                    percentageChange = Number(product.percentage_change);
                }
                
                // Columna precio anterior
                let previousPriceHTML = '<span style="color: #999;">-</span>';
                if (previousPrice && Math.abs(previousPrice - currentPrice) > 0.001) {
                    previousPriceHTML = `<span style="color: #999; text-decoration: line-through;">${previousPrice.toFixed(2)}€</span>`;
                }
                
                // Columna cambio
                let changeHTML = '<span style="color: #999;">-</span>';
                if (priceChange !== 0 && ! isNaN(priceChange)) {
                    const isIncrease = priceChange > 0;
                    const arrow = isIncrease ? '↑' : '↓';
                    const color = isIncrease ? '#e74c3c' : '#27ae60';
                    const sign = isIncrease ? '+' : '';
                    
                    changeHTML = `
                        <div style="color: ${color}; font-weight: bold; white-space: nowrap;">
                            ${arrow} ${sign}${Math.abs(priceChange).toFixed(2)}€
                            <br>
                            <small style="font-size: 0.85em;">(${sign}${Math.abs(percentageChange).toFixed(2)}%)</small>
                        </div>
                    `;
                }
                
                // ✅ Fecha del último cambio
                let changeDateHTML = '<span style="color: #999;">-</span>';
                if (product.last_price_change_date) {
                    changeDateHTML = new Date(product.last_price_change_date).toLocaleString('es-ES', {dateStyle: 'short', timeStyle: 'short'});
                }
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${product.id}</td>
                    <td><a href="${product.url}" target="_blank" style="color: #4CAF50; text-decoration:  none; font-weight: 500;">${product.name}</a></td>
                    <td class="reference">${product.reference || '-'}</td>
                    <td class="price">${currentPrice.toFixed(2)}€</td>
                    <td style="text-align: center;">${previousPriceHTML}</td>
                    <td style="text-align: center;">${changeHTML}</td>
                    <td style="font-size: 0.9em; text-align: center;">${changeDateHTML}</td>
                    <td style="font-size: 0.9em;">${new Date(product.last_scraped_at || product.created_at).toLocaleString('es-ES', {dateStyle: 'short', timeStyle: 'short'})}</td>
                    <td style="text-align: center;"><span class="view-history" onclick="viewHistory(${product.id})">📈 Ver</span></td>
                `;
                tbody.appendChild(row);
            });
        }
        
        function renderPagination() {
            const totalPages = Math.ceil(filteredProducts.length / productsPerPage);
            
            let paginationHTML = '<div style="display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 30px; flex-wrap: wrap;">';
            
            if (currentPage > 1) {
                paginationHTML += `<button onclick="goToPage(${currentPage - 1})" class="btn-secondary" style="padding: 8px 15px;">← Anterior</button>`;
            }
            
            const maxButtons = 7;
            let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
            let endPage = Math.min(totalPages, startPage + maxButtons - 1);
            
            if (endPage - startPage < maxButtons - 1) {
                startPage = Math.max(1, endPage - maxButtons + 1);
            }
            
            if (startPage > 1) {
                paginationHTML += `<button onclick="goToPage(1)" class="btn-secondary" style="padding: 8px 12px;">1</button>`;
                if (startPage > 2) {
                    paginationHTML += `<span style="padding: 8px;">...</span>`;
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const isActive = i === currentPage;
                const btnClass = isActive ? 'btn-primary' : 'btn-secondary';
                const extraStyle = isActive ? 'font-weight: bold;' : '';
                paginationHTML += `<button onclick="goToPage(${i})" class="${btnClass}" style="padding: 8px 12px; ${extraStyle}">${i}</button>`;
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationHTML += `<span style="padding: 8px;">...</span>`;
                }
                paginationHTML += `<button onclick="goToPage(${totalPages})" class="btn-secondary" style="padding: 8px 12px;">${totalPages}</button>`;
            }
            
            if (currentPage < totalPages) {
                paginationHTML += `<button onclick="goToPage(${currentPage + 1})" class="btn-secondary" style="padding: 8px 15px;">Siguiente →</button>`;
            }
            
            paginationHTML += `
                <select onchange="changeProductsPerPage(this.value)" style="padding: 8px; border: 2px solid #ddd; border-radius: 8px; margin-left: 20px;">
                    <option value="25" ${productsPerPage === 25 ? 'selected' : ''}>25 por página</option>
                    <option value="50" ${productsPerPage === 50 ? 'selected' : ''}>50 por página</option>
                    <option value="100" ${productsPerPage === 100 ?  'selected' : ''}>100 por página</option>
                    <option value="250" ${productsPerPage === 250 ? 'selected' :  ''}>250 por página</option>
                </select>
            `;
            
            paginationHTML += '</div>';
            
            let paginationDiv = document.getElementById('pagination');
            if (! paginationDiv) {
                paginationDiv = document.createElement('div');
                paginationDiv.id = 'pagination';
                document.getElementById('products-table').after(paginationDiv);
            }
            paginationDiv.innerHTML = paginationHTML;
        }
        
        function goToPage(page) {
            currentPage = page;
            renderProductsWithPagination();
            document.getElementById('products-table').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        function changeProductsPerPage(value) {
            productsPerPage = parseInt(value);
            currentPage = 1;
            renderProductsWithPagination();
        }
        
        function filterProducts() {
            const searchText = document.getElementById('search-input').value.toLowerCase();
            const priceMin = parseFloat(document. getElementById('price-min').value) || 0;
            const priceMax = parseFloat(document. getElementById('price-max').value) || Infinity;
            const filterChanges = document.getElementById('filter-changes').value;
            const changeDateFrom = document.getElementById('change-date-from').value;
            const changeDateTo = document. getElementById('change-date-to').value;
            
            filteredProducts = allProducts.filter(product => {
                // Filtro de texto
                const matchText = searchText === '' || 
                    product.name.toLowerCase().includes(searchText) ||
                    (product.reference && product.reference. toLowerCase().includes(searchText));
                
                // Filtro de precio
                const price = parseFloat(product.current_price);
                const matchPrice = price >= priceMin && price <= priceMax;
                
                // ✅ Filtro de cambios
                let matchChange = true;
                const priceChange = product.price_change ?  Number(product.price_change) : 0;
                
                if (filterChanges === 'with-changes') {
                    matchChange = priceChange !== 0;
                } else if (filterChanges === 'increases') {
                    matchChange = priceChange > 0;
                } else if (filterChanges === 'decreases') {
                    matchChange = priceChange < 0;
                }
                
                // ✅ Filtro por fecha de cambio
                let matchDate = true;
                if (product.last_price_change_date) {
                    const changeDate = new Date(product.last_price_change_date);
                    
                    if (changeDateFrom) {
                        const dateFrom = new Date(changeDateFrom);
                        matchDate = matchDate && changeDate >= dateFrom;
                    }
                    
                    if (changeDateTo) {
                        const dateTo = new Date(changeDateTo);
                        dateTo.setHours(23, 59, 59, 999);
                        matchDate = matchDate && changeDate <= dateTo;
                    }
                } else if (changeDateFrom || changeDateTo) {
                    matchDate = false;
                }
                
                return matchText && matchPrice && matchChange && matchDate;
            });
            
            currentPage = 1;
            sortProducts();
            updateFilterResults();
        }
        
        function sortProducts() {
            const sortBy = document.getElementById('sort-by').value;
            
            filteredProducts.sort((a, b) => {
                switch(sortBy) {
                    case 'id-asc':
                        return a.id - b.id;
                    case 'id-desc':
                        return b.id - a. id;
                    case 'name-asc':
                        return a.name.localeCompare(b.name);
                    case 'name-desc': 
                        return b.name. localeCompare(a.name);
                    case 'price-asc':
                        return parseFloat(a.current_price) - parseFloat(b.current_price);
                    case 'price-desc':
                        return parseFloat(b.current_price) - parseFloat(a.current_price);
                    case 'date-desc':
                        return new Date(b.created_at) - new Date(a.created_at);
                    case 'change-desc':  // Mayor bajada (más negativo primero)
                        const changeA = Number(a.price_change || 0);
                        const changeB = Number(b.price_change || 0);
                        return changeA - changeB;
                    case 'change-asc': // Mayor subida (más positivo primero)
                        const changeA2 = Number(a.price_change || 0);
                        const changeB2 = Number(b.price_change || 0);
                        return changeB2 - changeA2;
                    case 'change-date-desc':  // Más reciente primero
                        const dateA = a.last_price_change_date ?  new Date(a.last_price_change_date) : new Date(0);
                        const dateB = b.last_price_change_date ? new Date(b.last_price_change_date) : new Date(0);
                        return dateB - dateA;
                    case 'change-date-asc': // Más antiguo primero
                        const dateA2 = a.last_price_change_date ? new Date(a.last_price_change_date) : new Date(0);
                        const dateB2 = b.last_price_change_date ? new Date(b.last_price_change_date) : new Date(0);
                        return dateA2 - dateB2;
                    default:
                        return 0;
                }
            });
            
            renderProductsWithPagination();
        }
        
        function clearFilters() {
            document. getElementById('search-input').value = '';
            document.getElementById('price-min').value = '';
            document.getElementById('price-max').value = '';
            document.getElementById('filter-changes').value = 'all';
            document.getElementById('change-date-from').value = '';
            document.getElementById('change-date-to').value = '';
            document.getElementById('sort-by').value = 'id-asc';
            
            filteredProducts = [... allProducts];
            currentPage = 1;
            renderProductsWithPagination();
            updateFilterResults();
            
            showMessage('Filtros limpiados');
        }
        
        function updateFilterResults() {
            const resultsDiv = document.getElementById('filter-results');
            const total = allProducts.length;
            const filtered = filteredProducts.length;
            const startIndex = (currentPage - 1) * productsPerPage + 1;
            const endIndex = Math.min(currentPage * productsPerPage, filtered);
            
            if (filtered === 0) {
                resultsDiv.innerHTML = `No se encontraron productos`;
            } else if (filtered === total) {
                resultsDiv.innerHTML = `Mostrando <strong>${startIndex}-${endIndex}</strong> de <strong>${total}</strong> productos`;
            } else {
                resultsDiv.innerHTML = `Mostrando <strong>${startIndex}-${endIndex}</strong> de <strong>${filtered}</strong> productos filtrados (${total} total)`;
            }
        }
        
        async function addProduct() {
            const urlInput = document.getElementById('product-url');
            const url = urlInput.value.trim();
            
            if (!url) {
                showMessage('Por favor ingresa una URL', 'error');
                return;
            }
            
            if (! url.includes('greenice.com')) {
                showMessage('La URL debe ser de greenice.com', 'error');
                return;
            }
            
            showMessage('Añadiendo producto...  esto puede tardar unos segundos', 'success');
            
            try {
                const response = await fetch(`${API_BASE}? endpoint=products`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ url })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showMessage('✓ Producto añadido correctamente');
                    urlInput.value = '';
                    loadProducts();
                    loadStats();
                } else {
                    showMessage(data.error || 'Error al añadir producto', 'error');
                }
            } catch (error) {
                console.error('Error adding product:', error);
                showMessage('Error al añadir producto', 'error');
            }
        }
        
        async function startFullScrape() {
            if (!confirm('¿Escanear todo el catálogo de GreenICE?  Puede tardar 5-10 minutos.')) return;
            
            showMessage('Iniciando escaneo del catálogo completo...  Esto puede tardar varios minutos.');
            
            try {
                const response = await fetch(`${API_BASE}?endpoint=products/scrape-shopify`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ max_products: 10000 })
                });
                
                const data = await response. json();
                
                if (data.success) {
                    showMessage(`✓ ¡Escaneo completado! ${data.products_created} productos añadidos, ${data.products_updated} actualizados de ${data.products_found} encontrados`);
                    loadProducts();
                    loadStats();
                } else {
                    showMessage(data.error || 'Error al escanear', 'error');
                }
            } catch (error) {
                console.error('Error scraping:', error);
                showMessage('Error al escanear catálogo', 'error');
            }
        }
        
        function refreshProducts() {
            loadProducts();
            loadStats();
            showMessage('Datos actualizados');
        }
        
        async function viewHistory(productId) {
            try {
                const response = await fetch(`${API_BASE}?endpoint=products/${productId}`);
                const data = await response.json();
                
                if (data.success) {
                    const modal = document.getElementById('history-modal');
                    const content = document.getElementById('history-content');
                    
                    let html = `<h2>${data.data.name}</h2><p><strong>Precio actual:</strong> ${parseFloat(data.data.current_price).toFixed(2)}€</p><br><h3>📊 Historial de cambios de precio: </h3>`;
                    
                    if (! data.price_history || data.price_history.length === 0) {
                        html += '<p style="color: #666; padding: 20px; text-align: center;">No hay cambios de precio registrados todavía</p>';
                    } else {
                        html += '<table style="width: 100%; margin-top: 15px;"><thead><tr><th>Fecha</th><th>Precio Anterior</th><th>Precio Nuevo</th><th>Cambio</th></tr></thead><tbody>';
                        
                        data.price_history.forEach(history => {
                            const changeClass = history.price_change > 0 ? 'color:  red' : 'color: green';
                            const arrow = history.price_change > 0 ? '↑' :  '↓';
                            html += `<tr>
                                <td>${new Date(history.recorded_at).toLocaleString('es-ES')}</td>
                                <td>${parseFloat(history.old_price).toFixed(2)}€</td>
                                <td>${parseFloat(history.new_price).toFixed(2)}€</td>
                                <td style="${changeClass}">${arrow} ${Math.abs(history.price_change).toFixed(2)}€ (${history.percentage_change > 0 ? '+' : ''}${parseFloat(history.percentage_change).toFixed(2)}%)</td>
                            </tr>`;
                        });
                        html += '</tbody></table>';
                    }
                    
                    content.innerHTML = html;
                    modal.style.display = 'block';
                }
            } catch (error) {
                console.error('Error loading history:', error);
                showMessage('Error al cargar historial', 'error');
            }
        }
        
        function closeHistoryModal() {
            document. getElementById('history-modal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('history-modal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>

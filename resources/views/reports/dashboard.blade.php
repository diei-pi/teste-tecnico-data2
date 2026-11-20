<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duarte Imóveis 🏡 | Gestor de Comissões</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-800 antialiased min-h-screen" x-data="dashboardApp()">

    <!-- Top Navigation Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <span class="text-2xl">🏡</span>
                <div>
                    <h1 class="text-lg font-bold text-slate-900 leading-tight">Duarte Imóveis</h1>
                    <p class="text-xs text-slate-500">Gestão Inteligente de Carteira & Comissões</p>
                </div>
            </div>

            <div class="flex items-center space-x-3">
                @if($reports->isNotEmpty())
                    <!-- Historical uploads selector -->
                    <div class="relative">
                        <select onchange="window.location.href = this.value" class="text-xs font-medium bg-slate-100 hover:bg-slate-200 text-slate-700 py-2 px-3 rounded-lg border-0 cursor-pointer focus:ring-2 focus:ring-emerald-500">
                            @foreach($reports as $rep)
                                <option value="{{ route('dashboard', ['report_id' => $rep->id]) }}" {{ $currentReport && $currentReport->id === $rep->id ? 'selected' : '' }}>
                                    📁 {{ $rep->filename }} ({{ $rep->created_at->format('d/m/Y H:i') }}) - {{ ucfirst($rep->status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <button @click="showUploadModal = true" class="inline-flex items-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                    </svg>
                    Subir Nova Planilha
                </button>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        <!-- Processing Banner with Polling -->
        @if($currentReport && $currentReport->status === 'processing')
            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 rounded-r-lg shadow-sm flex items-center justify-between" x-init="startPolling({{ $currentReport->id }})">
                <div class="flex items-center space-x-3">
                    <svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-blue-900">Processando planilha em segundo plano...</p>
                        <p class="text-xs text-blue-700">Normalizando moedas, limpando cidades e calculando regras de comissão com corte no dia 20.</p>
                    </div>
                </div>
                <span class="text-xs font-mono bg-blue-100 text-blue-800 px-2.5 py-1 rounded">Fila Assíncrona Ativa</span>
            </div>
        @elseif($currentReport && $currentReport->status === 'pending')
            <div class="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-lg shadow-sm flex items-center justify-between" x-init="startPolling({{ $currentReport->id }})">
                <div class="flex items-center space-x-3">
                    <span class="animate-pulse h-3 w-3 rounded-full bg-amber-500"></span>
                    <div>
                        <p class="text-sm font-semibold text-amber-900">Arquivo na fila de processamento...</p>
                        <p class="text-xs text-amber-700">Aguardando o worker de fila iniciar a execução.</p>
                    </div>
                </div>
            </div>
        @elseif($currentReport && $currentReport->status === 'failed')
            <div class="bg-rose-50 border-l-4 border-rose-500 p-4 rounded-r-xl shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-start space-x-3">
                    <span class="text-2xl">⚠️</span>
                    <div>
                        <h3 class="text-sm font-bold text-rose-900">Erro no processamento da planilha</h3>
                        <p class="text-xs text-rose-700 mt-0.5">
                            {{ $currentReport->error_message ?? 'Ocorreu uma falha inesperada durante a leitura deste arquivo.' }}
                        </p>
                        <p class="text-xs font-semibold text-rose-800 mt-1">
                            Por favor, <strong>entre em contato com o suporte técnico</strong> informando o ID #{{ $currentReport->id }} e o arquivo "{{ $currentReport->filename }}".
                        </p>
                    </div>
                </div>
                <div class="flex items-center space-x-2 shrink-0">
                    <a href="mailto:suporte@duarteimoveis.com?subject=Falha%20no%20Relat%C3%B3rio%20%23{{ $currentReport->id }}%20-%20{{ urlencode($currentReport->filename) }}" class="inline-flex items-center px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg shadow-sm transition">
                        Contatar Suporte
                    </a>
                </div>
            </div>
        @endif

        @if(!$currentReport)
            <!-- Empty state when no report has been uploaded yet -->
            <div class="text-center py-16 bg-white rounded-2xl border border-dashed border-slate-300 shadow-sm">
                <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
                    📊
                </div>
                <h2 class="text-xl font-bold text-slate-800">Nenhuma carteira importada ainda</h2>
                <p class="text-sm text-slate-500 max-w-md mx-auto mt-2">
                    Faça o upload do arquivo <span class="font-mono bg-slate-100 px-1 py-0.5 rounded text-slate-700">carteira_sabrina_nov2026.csv</span> para visualizar as comissões apuradas automaticamente.
                </p>
                <button @click="showUploadModal = true" class="mt-6 inline-flex items-center px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm rounded-xl shadow-md transition">
                    Carregar Planilha Agora
                </button>
            </div>
        @elseif($currentReport->status === 'failed')
            <!-- Detailed Support Card when report fails -->
            <div class="bg-white rounded-2xl border border-rose-200 p-8 shadow-sm text-center max-w-2xl mx-auto space-y-4">
                <div class="w-16 h-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto text-3xl">
                    ⚠️
                </div>
                <h3 class="text-lg font-bold text-slate-900">Não foi possível processar esta planilha</h3>
                <p class="text-sm text-slate-600 leading-relaxed">
                    Ocorreu um erro durante a validação ou o cálculo dos dados do arquivo 
                    <strong class="text-slate-800 font-mono">{{ $currentReport->filename }}</strong>. 
                    Seus dados de relatórios anteriores continuam salvos e seguros no histórico.
                </p>

                <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 text-left text-xs space-y-2">
                    <div class="flex justify-between text-rose-900 font-semibold border-b border-rose-200/60 pb-1.5">
                        <span>Protocolo de Erro: #{{ $currentReport->id }}</span>
                        <span>{{ $currentReport->created_at->format('d/m/Y H:i') }}</span>
                    </div>
                    <p class="text-rose-800 font-mono break-words">
                        {{ $currentReport->error_message ?? 'Falha técnica no worker de fila ao processar lote de dados.' }}
                    </p>
                </div>

                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600 text-left space-y-1">
                    <p class="font-bold text-slate-800">📌 Como resolver:</p>
                    <p>1. Verifique se o arquivo possui colunas válidas no padrão CSV.</p>
                    <p>2. Se o problema persistir, <strong>entre em contato com nosso time de suporte</strong> informando o número do protocolo acima para análise imediata.</p>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                    <button @click="showUploadModal = true" class="w-full sm:w-auto px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                        Tentar Novo Upload
                    </button>
                    <a href="mailto:suporte@duarteimoveis.com?subject=Falha%20no%20Processamento%20-%20Protocolo%20%23{{ $currentReport->id }}" class="w-full sm:w-auto inline-flex items-center justify-center px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-xl shadow transition">
                        ✉️ Entrar em Contato com o Suporte
                    </a>
                </div>
            </div>
        @elseif($monthData)

            <!-- Month Selection & Cycle Info Bar -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                <div class="flex items-center space-x-3">
                    <label for="month-select" class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Mês de Referência:</label>
                    <select id="month-select" onchange="window.location.href = '{{ route('dashboard', ['report_id' => $currentReport->id]) }}&month=' + this.value" class="text-sm font-bold text-slate-900 bg-slate-50 border border-slate-300 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-emerald-500">
                        @foreach($availableMonths as $m)
                            @php
                                [$y, $numM] = explode('-', $m);
                                $names = ['01'=>'Janeiro','02'=>'Fevereiro','03'=>'Março','04'=>'Abril','05'=>'Maio','06'=>'Junho','07'=>'Julho','08'=>'Agosto','09'=>'Setembro','10'=>'Outubro','11'=>'Novembro','12'=>'Dezembro'];
                                $label = ($names[$numM] ?? $numM) . " / " . $y;
                            @endphp
                            <option value="{{ $m }}" {{ $selectedMonth === $m ? 'selected' : '' }}>
                                {{ $label }} {{ $m === '2026-11' ? '★ (Oficial)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center space-x-2 text-xs text-slate-600 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>Ciclo Contábil: <strong>{{ $monthData['cycle_start'] }}</strong> até <strong>{{ $monthData['cycle_end'] }}</strong> (Corte no dia 20)</span>
                </div>
            </div>

            <!-- KPI Metric Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5">

                <!-- Card 1: Comissão Oficial Garantida -->
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow transition relative overflow-hidden">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">Garantido / Caixa</span>
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold">R$</div>
                    </div>
                    <div class="text-3xl font-extrabold text-slate-900 tracking-tight">
                        R$ {{ number_format($monthData['totals']['total_garantido'], 2, ',', '.') }}
                    </div>
                    <p class="text-xs text-slate-500 mt-2">
                        Fechado para o contador: 
                        <strong class="text-slate-700">R$ {{ number_format($monthData['totals']['total_vendas'], 2, ',', '.') }}</strong> vendas + 
                        <strong class="text-slate-700">R$ {{ number_format($monthData['totals']['total_aluguel_novo'] + $monthData['totals']['total_aluguel_recorrente'], 2, ',', '.') }}</strong> aluguéis.
                    </p>
                </div>

                <!-- Card 2: Pipeline de Negociações -->
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow transition relative overflow-hidden">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full">Previsão / Pipeline</span>
                        <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center font-bold">⏳</div>
                    </div>
                    <div class="text-3xl font-extrabold text-slate-900 tracking-tight">
                        R$ {{ number_format($monthData['totals']['total_pipeline'], 2, ',', '.') }}
                    </div>
                    <p class="text-xs text-slate-500 mt-2">
                        Propostas em andamento que dependem do fechamento para virar caixa.
                    </p>
                </div>

                <!-- Card 3: Inadimplência / Pendência -->
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow transition relative overflow-hidden">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-rose-700 bg-rose-50 px-2.5 py-1 rounded-full">Alerta de Atraso</span>
                        <div class="w-8 h-8 rounded-lg bg-rose-100 text-rose-600 flex items-center justify-center font-bold">⚠️</div>
                    </div>
                    <div class="text-3xl font-extrabold text-slate-900 tracking-tight">
                        R$ {{ number_format($monthData['totals']['total_pendente'], 2, ',', '.') }}
                    </div>
                    <p class="text-xs text-slate-500 mt-2">
                        Aluguéis com atraso relatado (reconhecido apenas na liquidação).
                    </p>
                </div>

                <!-- Card 4: Imóveis no Portfólio -->
                <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm hover:shadow transition relative overflow-hidden">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-700 bg-slate-100 px-2.5 py-1 rounded-full">Carteira Ativa</span>
                        <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center font-bold">📋</div>
                    </div>
                    <div class="text-3xl font-extrabold text-slate-900 tracking-tight">
                        {{ $monthData['totals']['imoveis_count'] }} <span class="text-sm font-normal text-slate-500">imóveis</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">
                        Total de propriedades gerenciadas no histórico da Duarte Imóveis.
                    </p>
                </div>

            </div>

            <!-- Detail Table Section with Local Reactive Search/Filters via Alpine -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" x-data="{
                search: '',
                selectedCity: 'all',
                selectedCategory: 'all',
                perPage: 100,
                currentPage: 1,
                items: {{ json_encode($monthData['items']) }},
                get filteredItems() {
                    return this.items.filter(item => {
                        const matchSearch = this.search === '' ||
                            item.codigo.toLowerCase().includes(this.search.toLowerCase()) ||
                            item.bairro.toLowerCase().includes(this.search.toLowerCase()) ||
                            item.tipo.toLowerCase().includes(this.search.toLowerCase());
                        
                        const matchCity = this.selectedCity === 'all' || item.cidade === this.selectedCity;
                        
                        const matchCategory = this.selectedCategory === 'all' || item.categoria === this.selectedCategory;

                        return matchSearch && matchCity && matchCategory;
                    });
                },
                get totalPages() {
                    return Math.ceil(this.filteredItems.length / this.perPage) || 1;
                },
                get paginatedItems() {
                    const start = (this.currentPage - 1) * this.perPage;
                    return this.filteredItems.slice(start, start + this.perPage);
                },
                changePage(page) {
                    const p = Number(page);
                    if (p >= 1 && p <= this.totalPages) {
                        this.currentPage = p;
                    }
                },
                resetPage() {
                    this.currentPage = 1;
                }
            }">

                <!-- Table Header & Controls -->
                <div class="p-5 border-b border-slate-200 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">Detalhamento dos Imóveis & Regras de Comissão</h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Visão transparente e auditável por imóvel para conferência contábil
                            @if(!empty($monthData['is_truncated']))
                                <span class="ml-2 font-medium text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded">
                                    Exibindo primeiros 10.000 de {{ number_format($monthData['total_items_count'], 0, ',', '.') }} imóveis
                                </span>
                            @endif
                        </p>
                    </div>

                    <!-- Search and Filters -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Search Box -->
                        <div class="relative">
                            <input type="text" x-model="search" @input="resetPage()" placeholder="Buscar imóvel ou bairro..." class="w-48 sm:w-60 text-xs bg-slate-50 border border-slate-300 rounded-lg pl-8 pr-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:bg-white">
                            <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>

                        <!-- City Filter -->
                        <select x-model="selectedCity" @change="resetPage()" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 focus:ring-2 focus:ring-emerald-500">
                            <option value="all">Todas as Cidades</option>
                            @php
                                $cities = array_unique(array_column($monthData['items'], 'cidade'));
                                sort($cities);
                            @endphp
                            @foreach($cities as $c)
                                <option value="{{ $c }}">{{ $c }}</option>
                            @endforeach
                        </select>

                        <!-- Category Filter -->
                        <select x-model="selectedCategory" @change="resetPage()" class="text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-2 focus:ring-2 focus:ring-emerald-500">
                            <option value="all">Todas as Categorias</option>
                            <option value="venda_fechada">Vendas Fechadas (6%)</option>
                            <option value="aluguel_novo">Aluguel Novo (100%)</option>
                            <option value="aluguel_recorrente">Aluguel Recorrente (10%)</option>
                            <option value="pipeline">Em Negociação</option>
                            <option value="pendente">Atrasados / Pendentes</option>
                            <option value="reservado">Reservados</option>
                        </select>
                    </div>
                </div>

                <!-- Responsive Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                        <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider font-semibold">
                            <tr>
                                <th class="px-5 py-3.5">Código / Tipo</th>
                                <th class="px-4 py-3.5">Localização</th>
                                <th class="px-4 py-3.5">Operação & Status</th>
                                <th class="px-4 py-3.5">Valor Base</th>
                                <th class="px-4 py-3.5">Regra Aplicada & Observação</th>
                                <th class="px-5 py-3.5 text-right">Comissão Apurada</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <template x-for="item in paginatedItems" :key="item.codigo">
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="px-5 py-4 whitespace-nowrap">
                                        <div class="font-bold text-slate-900" x-text="item.codigo"></div>
                                        <div class="text-slate-500 text-[11px]" x-text="item.tipo"></div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="font-medium text-slate-800" x-text="item.bairro"></div>
                                        <div class="text-slate-500 text-[11px]" x-text="item.cidade"></div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold"
                                              :class="item.negocio === 'Venda' ? 'bg-indigo-50 text-indigo-700' : 'bg-teal-50 text-teal-700'"
                                              x-text="item.negocio"></span>
                                        <div class="text-slate-600 mt-1 font-medium" x-text="item.status"></div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="font-semibold text-slate-900" x-text="'R$ ' + Number(item.preco_base_calculo).toLocaleString('pt-BR', {minimumFractionDigits: 2})"></div>
                                        <div class="text-[10px] text-slate-400" x-text="item.preco_fechado ? 'Preço Fechado' : 'Preço Anunciado'"></div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium" :class="item.badge_class" x-text="item.regra_aplicada"></span>
                                        <template x-if="item.observacao">
                                            <p class="text-[11px] text-slate-500 mt-1 italic" x-text="'Nota: ' + item.observacao"></p>
                                        </template>
                                    </td>
                                    <td class="px-5 py-4 whitespace-nowrap text-right">
                                        <template x-if="item.comissao_oficial > 0">
                                            <div>
                                                <span class="text-sm font-bold text-emerald-600" x-text="'R$ ' + Number(item.comissao_oficial).toLocaleString('pt-BR', {minimumFractionDigits: 2})"></span>
                                                <div class="text-[10px] text-emerald-700 font-medium">Garantido</div>
                                            </div>
                                        </template>
                                        <template x-if="item.comissao_pipeline > 0">
                                            <div>
                                                <span class="text-sm font-bold text-amber-600" x-text="'R$ ' + Number(item.comissao_pipeline).toLocaleString('pt-BR', {minimumFractionDigits: 2})"></span>
                                                <div class="text-[10px] text-amber-700 font-medium">Previsão</div>
                                            </div>
                                        </template>
                                        <template x-if="item.comissao_pendente > 0">
                                            <div>
                                                <span class="text-sm font-bold text-rose-600" x-text="'R$ ' + Number(item.comissao_pendente).toLocaleString('pt-BR', {minimumFractionDigits: 2})"></span>
                                                <div class="text-[10px] text-rose-700 font-medium">Atrasado</div>
                                            </div>
                                        </template>
                                        <template x-if="item.comissao_oficial === 0 && item.comissao_pipeline === 0 && item.comissao_pendente === 0">
                                            <span class="text-xs text-slate-400 font-mono">R$ 0,00</span>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="filteredItems.length === 0">
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-slate-400 text-sm">
                                        Nenhum imóvel encontrado para os filtros selecionados.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="px-5 py-4 border-t border-slate-200 bg-slate-50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <!-- Quantity per page & Summary info -->
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center space-x-2">
                            <label for="per-page-select" class="text-xs text-slate-500 font-medium">Exibir:</label>
                            <select id="per-page-select" x-model.number="perPage" @change="resetPage()" class="text-xs bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 focus:ring-2 focus:ring-emerald-500 font-semibold text-slate-700 shadow-sm">
                                <option value="10">10 por página</option>
                                <option value="100">100 por página (padrão)</option>
                                <option value="1000">1.000 por página</option>
                            </select>
                        </div>

                        <span class="text-xs text-slate-500">
                            Mostrando 
                            <strong class="text-slate-700" x-text="filteredItems.length === 0 ? 0 : ((currentPage - 1) * perPage + 1)"></strong>
                            a 
                            <strong class="text-slate-700" x-text="Math.min(currentPage * perPage, filteredItems.length)"></strong> 
                            de 
                            <strong class="text-slate-700" x-text="filteredItems.length"></strong> imóveis
                        </span>
                    </div>

                    <!-- Page navigation buttons -->
                    <div class="flex items-center space-x-2">
                        <button 
                            @click="changePage(currentPage - 1)" 
                            :disabled="currentPage === 1" 
                            class="inline-flex items-center px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition">
                            ← Anterior
                        </button>

                        <div class="flex items-center space-x-1">
                            <span class="text-xs text-slate-500 px-1">Página</span>
                            <select 
                                x-model.number="currentPage" 
                                class="text-xs bg-white border border-slate-300 rounded-lg px-2 py-1 font-bold text-slate-800 shadow-sm focus:ring-2 focus:ring-emerald-500">
                                <template x-for="p in totalPages" :key="p">
                                    <option :value="p" x-text="p"></option>
                                </template>
                            </select>
                            <span class="text-xs text-slate-500 px-1" x-text="'de ' + totalPages"></span>
                        </div>

                        <button 
                            @click="changePage(currentPage + 1)" 
                            :disabled="currentPage >= totalPages" 
                            class="inline-flex items-center px-3 py-1.5 bg-white border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition">
                            Próxima →
                        </button>
                    </div>
                </div>

            </div>

        @endif

    </main>

    <!-- Upload Modal -->
    <div x-show="showUploadModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl relative" @click.away="showUploadModal = false">
            <div class="flex items-center justify-between pb-4 border-b border-slate-200">
                <div class="flex items-center space-x-2">
                    <span class="text-xl">📤</span>
                    <h3 class="text-base font-bold text-slate-900">Upload de Carteira CSV</h3>
                </div>
                <button @click="showUploadModal = false" class="text-slate-400 hover:text-slate-600 text-lg">&times;</button>
            </div>

            <form action="{{ route('reports.store') }}" method="POST" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <div class="border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-xl p-6 text-center cursor-pointer transition">
                    <input type="file" name="csv_file" id="csv_file" required accept=".csv,.txt" class="w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    <p class="text-xs text-slate-400 mt-2">Suporta qualquer planilha histórica (.csv)</p>
                </div>

                <div class="bg-slate-50 p-3 rounded-lg text-xs text-slate-600 space-y-1">
                    <p class="font-medium text-slate-800">⚡ O que acontece ao enviar:</p>
                    <p>• O arquivo é enviado para processamento em <strong>fila assíncrona</strong>.</p>
                    <p>• Valores monetários bagunçados (ex: "720 mil", "R$ 3.500/mês") são higienizados.</p>
                    <p>• O cálculo aplica automaticamente a regra de corte no <strong>dia 20</strong>.</p>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-3 border-t border-slate-200">
                    <button type="button" @click="showUploadModal = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
                    <button type="submit" class="px-5 py-2 text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg shadow transition">Iniciar Processamento</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Alpine Dashboard Store & Script -->
    <script>
        function dashboardApp() {
            return {
                showUploadModal: false,
                startPolling(reportId) {
                    const interval = setInterval(() => {
                        fetch(`/reports/${reportId}/status`)
                            .then(res => res.json())
                            .then(data => {
                                if (data.status === 'completed' || data.status === 'failed') {
                                    clearInterval(interval);
                                    window.location.reload();
                                }
                            })
                            .catch(err => console.error('Polling error:', err));
                    }, 1500);
                }
            }
        }
    </script>
</body>
</html>

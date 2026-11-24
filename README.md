# 🏢 Duarte Imóveis — Apuração e Fechamento de Comissões
### 🎯 Teste Técnico — Data2

Uma plataforma robusta, escalável e resiliente para ingestão de planilhas imobiliárias (*messy data*), cálculo determinístico de comissões sob regime de caixa estrito e geração de relatórios contábeis para fechamento mensal.

Desenvolvido para resolver o desafio de negócio da fundadora **Sabrina Duarte (Duarte Imóveis)**: eliminar surpresas financeiras no caixa, blindar a empresa contra inadimplência, respeitar o corte de ciclo no dia 20 e escalar sem degradação desde 18 até mais de 100.000 registros.

---

## 📌 1. O Intuito do Projeto & Desafio de Negócio

No setor imobiliário, fechar comissões em planilhas manuais de Excel gera três grandes riscos de negócio:
1. **Surpresas no Fluxo de Caixa:** Pagar comissão de corretor antes de a escritura ser lavrada em cartório ou sobre aluguel que o inquilino atrasou provoca descasamento financeiro severo.
2. **Dados Despadronizados (*Messy Data*):** Planilhas reais contêm valores como `"720 mil"`, `"R$ 3.500/mês"`, `"1.150.000"` e anotações informais em campos de texto.
3. **Corte Operacional no Dia 20:** O mês financeiro da Duarte Imóveis não fecha no dia 30, mas sim no dia 20 (ciclo de 21 do mês anterior a 20 do mês vigente).

Esta solução automatiza 100% da esteira contábil, garantindo **auditabilidade linha a linha**, separação rigorosa entre **dinheiro real (Caixa)** e **previsão comercial (Pipeline)**, e processamento assíncrono de alta performance.

---

## 🚀 2. Principais Features

- **📊 Dashboard Financeiro Executivo:**
  - **Caixa Garantido:** Total líquido e certo a ser pago/declarado para o contador.
  - **Pipeline (Em Negociação):** Previsão comercial (*forecast*) segregada sem inflar a receita realizada.
  - **Inadimplência / Pendente:** Valores contingenciados por atraso de pagamento, prontos para liquidação futura.
  - **Total de Imóveis:** Contagem global e controle da carteira.
- **⚡ Processamento em Escala Extrema (100.000+ linhas):**
  - Leitura via `\Generator` (`yield`) com consumo de memória $O(1)$ (~24 MB de RAM).
  - Agregação em **Passagem Única (*Single-Pass*)**: 100.000 registros processados em **6.5 segundos**.
  - Amostra auditável paginada (1.000 itens) no frontend com consolidação matemática de 100% dos dados nos KPIs.
- **🧹 Normalização Resiliente de Dados (*Messy Data Tolerance*):**
  - Parsing de moedas com texto misto (`"720 mil"`, `"R$3.500/mês"`, `"4.500,00"`) e datas heterogêneas (`d/m/Y`, `Y-m-d`).
  - Deteção automática de delimitadores (`;` ou `,`) e sanitização de UTF-8 BOM.
- **🔎 Filtros & Busca Reativa em Tempo Real:**
  - Busca instantânea por código do imóvel, tipo ou bairro via Alpine.js.
  - Filtros rápidos por cidade e por categoria contábil.
  - Seletor de paginação configurável (**10**, **100 [padrão]** e **1.000 itens por página**).
- **🛡️ Tratamento de Erros Humanizado:**
  - Falhas em planilhas geram tela de contingência com Protocolo técnico rastreável (`#ID`) e botão de suporte direto por e-mail.
- **🛠️ Engenharia & Clean Code:**
  - Motor de cálculo isolado no `CalculateCommissionService` sem cláusulas `else`/`elseif`, utilizando *guard clauses* e *early returns*.
  - Enums fortemente tipados (`BusinessType`, `PropertyStatus`, `CommissionCategory`).
  - Ambiente de depuração completo com **Xdebug 3.5** habilitado e comando Artisan síncrono para testes linha a linha.

---

## 📐 3. Como o Sistema Faz as Coisas (Regras de Negócio)

| Regra | Lógica Contábil | Exemplo no Portfólio (Nov/2026) |
|---|---|---|
| **Corte no Dia 20** | Ciclo vai de **21/(M-1) até 20/M**. Eventos após o dia 20 migram para o mês seguinte. | `IMV-013` (fechado em 20/11) entra em Novembro.<br>`IMV-018` (escritura em 10/12) diferido para Dezembro. |
| **Venda Fechada (6%)** | 6% sobre o `preco_fechado` reconhecido **apenas no mês da assinatura da escritura**. Vendas passadas geram R$ 0 de comissão no mês. | `IMV-001` (R$ 950k × 6% = R$ 57.000)<br>`IMV-013` (R$ 400k × 6% = R$ 24.000)<br>**Total Vendas: R$ 81.000,00** |
| **Aluguel Novo (100%)** | 100% do primeiro aluguel para novos contratos iniciados no ciclo. | `IMV-003` (R$ 2.800) + `IMV-011` (R$ 3.100)<br>**Total Aluguel Novo: R$ 5.900,00** |
| **Aluguel Recorrente (10%)** | 10% mensal sobre o valor da locação para contratos do mês 2 em diante. | `IMV-004` (R$ 350) + `IMV-008` (R$ 160) + `IMV-014` (R$ 500)<br>**Total Recorrente: R$ 1.010,00** |
| **Inadimplência (Caixa Estrito)** | Aluguel com anotação de atraso gera **R$ 0,00 de caixa** e é provisionado em "Pendente". | `IMV-017` (aluguel R$ 4.500 atrasou): **R$ 0,00 Caixa** / **R$ 450,00 Pendente** |
| **Pipeline (Em Negociação)** | Projeção calculada mas rigorosamente segregada do caixa. | `IMV-006` + `IMV-010` + `IMV-016`<br>**Total Pipeline: R$ 70.200,00** |

**Totais Consolidados de Novembro/2026:**
- **Caixa Garantido Oficial:** **R$ 87.910,00** ($81.000 + 5.900 + 1.010$)
- **Pipeline Estimado:** **R$ 70.200,00**
- **Pendente Contingenciado:** **R$ 450,00**

---

## 🛠️ 4. Como Rodar o Projeto

### Opção A: Via Docker Compose (Recomendado)

O projeto está totalmente conteinerizado com PHP 8.4 CLI Alpine, SQLite e Xdebug 3.5.

1. **Clonar o repositório:**
   ```bash
   git clone git@github.com:diei-pi/teste-tecnico-data2.git
   cd teste-tecnico-data2
   ```

2. **Subir os containers:**
   ```bash
   docker-compose up -d --build
   ```
   *O container `app` subirá o servidor web e o `queue` iniciará o worker assíncrono de fila.*

3. **Acessar a aplicação:**
   Abra no navegador: [**http://localhost:8080**](http://localhost:8080)  
   *(A porta 8080 foi escolhida para evitar conflitos na porta 8000).*

---

### Opção B: Rodar os Testes Automatizados

A suíte conta com 10 testes automatizados cobrindo regras de cálculo, casos de borda do dia 20, parsers de moeda/data e jobs de fila:

```bash
docker-compose exec app php artisan test
```

**Resultado esperado:**
```
PASS  Tests\Unit\CalculateCommissionServiceTest
PASS  Tests\Unit\DateHelperTest
PASS  Tests\Unit\ExampleTest
PASS  Tests\Unit\MoneyHelperTest
PASS  Tests\Unit\NormalizeCsvServiceTest
PASS  Tests\Feature\ExampleTest
PASS  Tests\Feature\ReportProcessingTest

Tests: 10 passed (59 assertions)
Duration: ~0.5s
```

---

### Opção C: Executar o Benchmark de 100.000 Linhas

Para validar o requisito não-funcional de alta escala:

```bash
# Executa o processamento manual síncrono com cronometragem e uso de memória
docker-compose exec app php artisan portfolio:process 1
```

**Resultado do teste de estresse:**
```
+-------------------------+-------------------+
| Métrica                 | Valor             |
+-------------------------+-------------------+
| Total Garantido (Caixa) | R$ 336.253.104,00 |
| Total Pipeline          | R$ 722.135.420,00 |
| Total Pendente/Atrasado | R$ 336.430,00     |
| Total Imóveis           | 100.000           |
+-------------------------+-------------------+
✅ Sucesso em 6.52 segundos com pico de RAM < 25 MB!
```

---

### Opção D: Depuração com Xdebug 3.5

O container já possui o Xdebug 3.5 instalado e configurado na porta `9003`:

1. No VS Code ou PHPStorm, inicie o listener do Xdebug ("Listen for Xdebug").
2. Para debugar rotas Web, basta fazer requisições normais no navegador.
3. Para debugar o processamento de planilhas via CLI linha a linha, execute:
   ```bash
   docker-compose exec -e XDEBUG_SESSION=1 app php artisan portfolio:process 2
   ```

---

## 🌐 5. Deploy em Nuvem (Render / Docker)

O projeto possui um script de bootstrap em [`docker/entrypoint.sh`](docker/entrypoint.sh) projetado para serviços em nuvem conteinerizados (como [Render](https://render.com) ou [Koyeb](https://koyeb.com)):

- Gera automaticamente chaves e ambiente se não existirem.
- Cria e migra o banco de dados SQLite.
- Executa a seeder inicial de Novembro/2026.
- Executa o **Queue Worker em segundo plano** e o **Servidor Web** no mesmo container gratuito.

---

## 📁 6. Estrutura Arquitetural

```
├── app/
│   ├── Console/Commands/
│   │   └── ProcessPortfolioCommand.php   # Comando Artisan para debug síncrono
│   ├── Enums/
│   │   ├── BusinessType.php              # Enum: Venda, Aluguel, Outro
│   │   ├── CommissionCategory.php        # Enum: Categorias de comissão & classes visuais
│   │   └── PropertyStatus.php            # Enum: Vendido, Alugado, EmNegociacao, etc.
│   ├── Helpers/
│   │   ├── DateHelper.php                # Parser multi-formato de datas
│   │   └── MoneyHelper.php               # Parser tolerante para moedas brasileiras
│   ├── Http/Controllers/
│   │   └── ReportController.php          # Upload de CSV, dashboard e polling
│   ├── Jobs/
│   │   └── ProcessPortfolioCsvJob.php    # Job assíncrono com captura de falhas
│   ├── Models/
│   │   └── Report.php                    # Model com snapshot JSON imutável
│   └── Services/
│       ├── CalculateCommissionService.php# Motor nuclear de regras de comissões
│       ├── NormalizeCsvService.php       # Parser CSV streaming com Generator (O(1))
│       └── ProcessPortfolioCsvService.php# Orquestrador do pipeline de processamento
├── docker/
│   └── entrypoint.sh                     # Script de inicialização para nuvem
├── Dockerfile                            # Imagem PHP 8.4 CLI Alpine com Xdebug
├── docker-compose.yml                    # Web server (8080) + Queue Worker
├── resources/views/reports/
│   └── dashboard.blade.php               # Dashboard reativo Blade + Tailwind + Alpine.js
└── tests/                                # Suíte automatizada com 10 testes
```

---

## 👤 Autor

**João Pedro**  
Engenheiro de Software & Product Owner  
*Duarte Imóveis — Projeto de Fechamento Contábil*

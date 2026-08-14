# CONTROLE DE CONTRATOS

Plugin para **GLPI 10.0.11+** de gestão e controle de **contratos e licenças**, com
alertas visuais modernos (Tabler), dashboard gerencial e disparo de notificações
multicanais de vencimento: **Web Push, Telegram, WhatsApp (Evolution API) e Microsoft Teams**.

**Autor:** João Pedro Castor Quirino

## Recursos

- CRUD de contratos/licenças (`CommonDBTM`, formulário Twig/Tabler nativo).
- Campo **Tipo** (Contrato × Licença) com badge/ícone próprios, inspirado na
  dropdown `ContractType` nativa do GLPI.
- Aba de **Anexos** via `Document_Item` (upload do PDF assinado etc.).
- Aba de **Preferências de Notificação** (toggles por canal, por usuário).
- **Tela de configuração das APIs** (Configurar → Plugins): todas as credenciais
  ficam no banco — nada hardcoded.
- **Dashboard** com indicadores: ativos, vencendo em 30/60 dias e vencidos.
- **Sino** na navbar (hook `display_header`) com badge e dropdown de vencimentos.
- **CronTask** diária que dispara os alertas conforme preferências e credenciais.

## Instalação (padrão — sem dependências)

1. Copie a pasta `controlecontratos/` para `glpi/plugins/`.
2. Em **Configurar → Plugins**, instale e ative o **CONTROLE DE CONTRATOS**.
3. Abra **CONTROLE DE CONTRATOS → Configuração das APIs** e preencha as credenciais
   do WhatsApp (Evolution), Telegram e/ou Teams.

Pronto. WhatsApp, Telegram e Teams usam apenas `cURL` (já presente no GLPI) —
**nenhuma biblioteca extra é necessária**.

## Web Push (OPCIONAL — notificação no navegador)

Só é preciso se você quiser esse canal específico. No servidor do GLPI:

```bash
cd glpi/plugins/controlecontratos
composer require minishlink/web-push
```

Depois preencha as **VAPID keys** na tela de configuração. Enquanto não configurado,
o Web Push fica dormente e não afeta o restante do plugin.

## Estrutura

```
controlecontratos/
├── setup.php                 # init + hooks + versão
├── hook.php                  # install/uninstall (tabelas, rights, cron) + display_header
├── composer.json             # dependência minishlink/web-push
├── inc/
│   ├── contract.class.php            # CommonDBTM principal (contratos/licenças)
│   ├── config.class.php              # GUI de configuração das APIs
│   ├── notificationpref.class.php    # preferências de canais por usuário
│   ├── notifier.class.php            # envios cURL (Teams/Telegram/WhatsApp)
│   ├── webpush.class.php             # entrega Web Push (VAPID)
│   ├── cron.class.php                # CronTask de vencimentos
│   ├── dashboard.class.php           # indicadores
│   └── menu.class.php                # menu do plugin
├── front/                    # controladores (search, form, config, dashboard)
├── ajax/                     # endpoints Web Push (config + subscribe)
├── templates/                # Twig (Tabler): contract, config, prefs, dashboard, bell
├── js/                       # webpush.js + service-worker.js
├── css/                      # estilos complementares
└── locales/                  # traduções (.pot/.po/.mo)
```

## Segurança

- Todos os formulários usam o **token CSRF** nativo (`_glpi_csrf_token`) e
  `Session::checkCSRF()` no processamento.
- Perfis controlados por `plugin_controlecontratos_contract` (rights).
- A **VAPID private key** nunca é exposta ao navegador.
- Credenciais são gravadas via `prepareInputForUpdate` com validação de URL.

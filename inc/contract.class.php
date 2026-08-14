<?php
/**
 * -------------------------------------------------------------------------
 * Classe principal de contratos — CONTROLE DE CONTRATOS.
 * Estende CommonDBTM seguindo o padrão GLPI 10+ (Tabler + Twig).
 *
 * @author João Pedro Castor Quirino
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

class PluginControlecontratosContract extends CommonDBTM
{
    /** @var string Direito associado ao objeto (perfis). */
    public static $rightname = 'plugin_controlecontratos_contract';

    /** @var bool Habilita histórico e uso de anexos (Document_Item). */
    public $dohistory = true;

    public static function getTypeName($nb = 0)
    {
        return _n('Contrato / Licença', 'Contratos / Licenças', $nb, 'controlecontratos');
    }

    /**
     * Ícone exibido no menu (Tabler icons).
     */
    public static function getIcon()
    {
        return 'ti ti-file-certificate';
    }

    /**
     * Define quais objetos podem receber a nossa aba e vice-versa.
     * Aqui declaramos as abas próprias: Anexos (Documentos) e Preferências.
     */
    public function defineTabs($options = [])
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab('Document_Item', $tabs, $options);            // Aba nativa de Anexos.
        $this->addStandardTab(__CLASS__, $tabs, $options);                  // Aba de Preferências (via getTabNameForItem).
        $this->addStandardTab('Log', $tabs, $options);                      // Histórico.
        return $tabs;
    }

    /**
     * Nome da aba adicional de Preferências de Notificação.
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof self && $item->getID() > 0) {
            return self::createTabEntry(
                __('Preferências de Notificação', 'controlecontratos'),
                0,
                $item::getType(),
                'ti ti-bell'
            );
        }
        return '';
    }

    /**
     * Conteúdo da aba de Preferências de Notificação.
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof self) {
            PluginControlecontratosNotificationpref::showForContract($item);
        }
        return true;
    }

    /**
     * Renderiza o formulário do contrato usando o template Twig (padrão GLPI 10).
     *
     * @param int   $ID
     * @param array $options
     * @return bool
     */
    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);

        // Renderização nativa via Twig — herda o layout Tabler e a responsividade.
        \Glpi\Application\View\TemplateRenderer::getInstance()->display('@controlecontratos/contract.html.twig', [
            'item'          => $this,
            'params'        => $options,
            'status_list'   => self::getStatusArray(),
            'kind_list'     => self::getKindArray(),
            'alert_list'    => self::getAlertOptions(),
            'period_list'   => self::getPeriodicityOptions(),
        ]);

        return true;
    }

    /**
     * Tipos de registro — diferencia Contrato de Licença.
     * Espelha a ideia da dropdown nativa ContractType do GLPI, mas fixa os
     * dois grandes grupos que exigem tratamento visual distinto.
     *
     * @return array<string, array{label:string, icon:string, color:string}>
     */
    public static function getKindArray()
    {
        return [
            'contract' => [
                'label' => __('Contrato', 'controlecontratos'),
                'icon'  => 'ti ti-file-certificate',
                'color' => 'bg-blue',
            ],
            'license' => [
                'label' => __('Licença', 'controlecontratos'),
                'icon'  => 'ti ti-license',
                'color' => 'bg-purple',
            ],
        ];
    }

    /**
     * Badge visual (ícone + cor) do tipo do registro (Contrato/Licença).
     *
     * @param string $kind
     * @return string HTML do badge.
     */
    public static function getKindBadge($kind)
    {
        $kinds = self::getKindArray();
        $k     = $kinds[$kind] ?? $kinds['contract'];
        return "<span class='badge {$k['color']}'><i class='{$k['icon']} me-1'></i>"
            . htmlspecialchars($k['label']) . "</span>";
    }

    /**
     * Opções do "Aviso de término" — antecedência em meses (valor guardado em dias).
     * Espelha o campo nativo de Aviso do módulo de Contratos do GLPI.
     *
     * @return array<int,string> [dias => rótulo]
     */
    public static function getAlertOptions()
    {
        return [
            0   => __('Não avisar', 'controlecontratos'),
            30  => __('1 mês (30 dias)', 'controlecontratos'),
            60  => __('2 meses (60 dias)', 'controlecontratos'),
            90  => __('3 meses (90 dias)', 'controlecontratos'),
            120 => __('4 meses (120 dias)', 'controlecontratos'),
            180 => __('6 meses (180 dias)', 'controlecontratos'),
            365 => __('12 meses (365 dias)', 'controlecontratos'),
        ];
    }

    /**
     * Opções de Periodicidade/duração do contrato (em meses).
     *
     * @return array<int,string>
     */
    public static function getPeriodicityOptions()
    {
        $opts = [0 => __('Indeterminada', 'controlecontratos')];
        foreach ([1, 3, 6, 12, 24, 36, 48, 60] as $m) {
            $opts[$m] = sprintf(_n('%d mês', '%d meses', $m, 'controlecontratos'), $m);
        }
        return $opts;
    }

    /**
     * Lista de status possíveis do contrato.
     */
    public static function getStatusArray()
    {
        return [
            'active'   => __('Ativo', 'controlecontratos'),
            'expired'  => __('Vencido', 'controlecontratos'),
            'canceled' => __('Cancelado', 'controlecontratos'),
            'renewed'  => __('Renovado', 'controlecontratos'),
        ];
    }

    /**
     * Retorna um "badge" Tabler colorido conforme o status/vencimento.
     *
     * @param array $row Linha do contrato (associativa).
     * @return string HTML do badge.
     */
    public static function getStatusBadge(array $row)
    {
        $today = new DateTime();
        $class = 'bg-secondary';
        $label = self::getStatusArray()[$row['status']] ?? $row['status'];

        if (!empty($row['date_end'])) {
            $end  = new DateTime($row['date_end']);
            $diff = (int) $today->diff($end)->format('%r%a');

            if ($diff < 0) {
                $class = 'bg-danger';
                $label = __('Vencido', 'controlecontratos');
            } elseif ($diff <= 30) {
                $class = 'bg-warning';
                $label = sprintf(__('Vence em %d dias', 'controlecontratos'), $diff);
            } elseif ($diff <= 60) {
                $class = 'bg-yellow';
                $label = sprintf(__('Vence em %d dias', 'controlecontratos'), $diff);
            } else {
                $class = 'bg-success';
            }
        }
        return "<span class='badge $class'>" . htmlspecialchars($label) . "</span>";
    }

    /**
     * Opções de pesquisa (Search Engine nativo do GLPI).
     */
    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = ['id' => 'common', 'name' => self::getTypeName(2)];

        $tab[] = [
            'id'            => '1',
            'table'         => self::getTable(),
            'field'         => 'name',
            'name'          => __('Nome', 'controlecontratos'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'         => '7',
            'table'      => self::getTable(),
            'field'      => 'kind',
            'name'       => __('Tipo', 'controlecontratos'),
            'datatype'   => 'specific',
            'searchtype' => 'equals',
        ];
        $tab[] = [
            'id'       => '2',
            'table'    => self::getTable(),
            'field'    => 'partner',
            'name'     => __('Fornecedor', 'controlecontratos'),
            'datatype' => 'string',
        ];
        $tab[] = [
            'id'       => '3',
            'table'    => self::getTable(),
            'field'    => 'date_begin',
            'name'     => __('Data de início', 'controlecontratos'),
            'datatype' => 'date',
        ];
        $tab[] = [
            'id'       => '4',
            'table'    => self::getTable(),
            'field'    => 'date_end',
            'name'     => __('Data de término', 'controlecontratos'),
            'datatype' => 'date',
        ];
        $tab[] = [
            'id'       => '6',
            'table'    => self::getTable(),
            'field'    => 'status',
            'name'     => __('Status', 'controlecontratos'),
            'datatype' => 'specific',
            'searchtype' => 'equals',
        ];
        $tab[] = [
            'id'       => '30',
            'table'    => self::getTable(),
            'field'    => 'id',
            'name'     => __('ID'),
            'datatype' => 'number',
            'massiveaction' => false,
        ];

        return $tab;
    }

    /**
     * Consulta reutilizável: contratos cujo término cai dentro de X dias
     * (ou já vencidos, se $onlyExpired). Base para o Dashboard, Sino e Cron.
     *
     * @param int         $days        Janela em dias (a partir de hoje).
     * @param bool        $onlyExpired Se true, retorna apenas os já vencidos.
     * @param string|null $kind        Filtra por tipo ('contract'|'license') ou null p/ todos.
     * @return array Linhas de contratos.
     */
    public static function getExpiringContracts($days = 30, $onlyExpired = false, $kind = null)
    {
        /** @var DBmysql $DB */
        global $DB;

        $today = date('Y-m-d');
        $limit = date('Y-m-d', strtotime("+$days days"));

        $where = [
            'is_deleted' => 0,
            'status'     => 'active',
        ] + getEntitiesRestrictCriteria(self::getTable());

        if ($kind !== null) {
            $where['kind'] = $kind;
        }

        if ($onlyExpired) {
            $where['date_end'] = ['<', $today];
        } else {
            // Faixa (>= hoje E <= limite) usando o array de critérios nativo do GLPI,
            // sem depender de QueryExpression (que mudou de namespace entre versões).
            $where[] = ['date_end' => ['>=', $today]];
            $where[] = ['date_end' => ['<=', $limit]];
        }

        $iterator = $DB->request([
            'FROM'   => self::getTable(),
            'WHERE'  => $where,
            'ORDER'  => 'date_end ASC',
        ]);

        return iterator_to_array($iterator);
    }

    /**
     * Métricas agregadas para o Dashboard (Tabler stat cards).
     *
     * @return array{active:int, next30:int, next60:int, expired:int}
     */
    public static function getDashboardStats($kind = null)
    {
        /** @var DBmysql $DB */
        global $DB;

        $baseNoKind = ['is_deleted' => 0] + getEntitiesRestrictCriteria(self::getTable());
        $base       = $baseNoKind;
        if ($kind !== null) {
            $base['kind'] = $kind;
        }

        // Contador que respeita o filtro de tipo ativo.
        $count = function (array $extra) use ($DB, $base) {
            return (int) ($DB->request([
                'COUNT' => 'cpt',
                'FROM'  => self::getTable(),
                'WHERE' => $base + $extra,
            ])->current()['cpt'] ?? 0);
        };
        // Contador do total por tipo (ignora o filtro ativo, p/ mostrar sempre o total de cada).
        $countAll = function (array $extra) use ($DB, $baseNoKind) {
            return (int) ($DB->request([
                'COUNT' => 'cpt',
                'FROM'  => self::getTable(),
                'WHERE' => $baseNoKind + $extra,
            ])->current()['cpt'] ?? 0);
        };

        return [
            'active'    => $count(['status' => 'active']),
            'next30'    => count(self::getExpiringContracts(30, false, $kind)),
            'next60'    => count(self::getExpiringContracts(60, false, $kind)),
            'expired'   => count(self::getExpiringContracts(0, true, $kind)),
            'contracts' => $countAll(['kind' => 'contract']),
            'licenses'  => $countAll(['kind' => 'license']),
        ];
    }

    /**
     * Renderiza o "Sino" de notificações na navbar via hook display_header.
     * Exibe um badge com a contagem e um dropdown com os contratos a vencer.
     *
     * @return void
     */
    public static function showNotificationBell()
    {
        // Dispensado nesta sessão → não exibe (volta só no próximo login).
        if (!empty($_SESSION['plugin_controlecontratos_bell_dismissed'])) {
            return;
        }

        $count = count(self::getExpiringContracts(30));

        // Não exibe o sino quando não há nada vencendo nos próximos 30 dias.
        if ($count === 0) {
            return;
        }

        \Glpi\Application\View\TemplateRenderer::getInstance()->display('@controlecontratos/bell.html.twig', [
            'count'       => $count,
            'dismiss_url' => Plugin::getWebDir('controlecontratos') . '/front/bell_dismiss.php',
        ]);
    }

    /**
     * Renderização dos campos 'specific' na listagem da busca.
     */
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'kind':
                return self::getKindBadge($values[$field]);
            case 'status':
                return self::getStatusArray()[$values[$field]] ?? $values[$field];
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    /**
     * Renderização dos campos 'specific' como filtros/dropdowns na busca.
     */
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'kind':
                $opts = [];
                foreach (self::getKindArray() as $key => $data) {
                    $opts[$key] = $data['label'];
                }
                return Dropdown::showFromArray($name, $opts, ['value' => $values[$field], 'display' => false]);
            case 'status':
                return Dropdown::showFromArray($name, self::getStatusArray(), ['value' => $values[$field], 'display' => false]);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /**
     * Ajusta valores antes de gravar (validação de datas e status).
     */
    public function prepareInputForAdd($input)
    {
        return $this->prepareInputForUpdate($input);
    }

    public function prepareInputForUpdate($input)
    {
        if (isset($input['date_begin'], $input['date_end'])
            && !empty($input['date_begin']) && !empty($input['date_end'])
            && $input['date_end'] < $input['date_begin']
        ) {
            Session::addMessageAfterRedirect(
                __('A data de término não pode ser anterior à data de início.', 'controlecontratos'),
                false,
                ERROR
            );
            return false;
        }

        // A coluna 'value' (decimal NOT NULL) foi removida da tela, mas continua no
        // banco. Garante que nunca chegue string vazia (que o MySQL rejeitaria).
        if (array_key_exists('value', $input) && ($input['value'] === '' || $input['value'] === null)) {
            $input['value'] = 0;
        }

        return $input;
    }
}

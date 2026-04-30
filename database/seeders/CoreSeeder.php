<?php

namespace Database\Seeders;

use App\Models\BillingGroup;
use App\Models\BillingStatus;
use App\Models\CashierPrinterAssignment;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OccupiedZone;
use App\Models\Printer;
use App\Models\PrinterRoute;
use App\Models\Row;
use App\Models\Seat;
use App\Models\SeatPair;
use App\Models\Section;
use App\Models\ServiceSession;
use App\Models\TranslationKey;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CoreSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Users ----
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name'                    => 'System Admin',
                'email'                   => 'admin@serveo.local',
                'password'                => Hash::make('password'),
                'preferred_language_code' => 'pt-PT',
                'is_active'               => true,
            ]
        );
        $admin->syncRoles(['ADMIN']);

        $cashier = User::updateOrCreate(
            ['username' => 'cashier1'],
            [
                'name'                    => 'Cashier One',
                'email'                   => 'cashier1@serveo.local',
                'password'                => Hash::make('password'),
                'preferred_language_code' => 'pt-PT',
                'is_active'               => true,
            ]
        );
        $cashier->syncRoles(['CASHIER']);

        $server = User::updateOrCreate(
            ['username' => 'server1'],
            [
                'name'                    => 'Server One',
                'email'                   => 'server1@serveo.local',
                'password'                => Hash::make('password'),
                'preferred_language_code' => 'pt-PT',
                'is_active'               => true,
            ]
        );
        $server->syncRoles(['SERVER']);

        // ---- Venue & service session ----
        $venue = Venue::updateOrCreate(
            ['venue_code' => 'MAIN'],
            ['name' => 'Salão Principal', 'is_active' => true]
        );

        $session = ServiceSession::updateOrCreate(
            ['venue_id' => $venue->id, 'session_label' => now()->toDateString().' DINNER'],
            [
                'session_type' => 'DINNER',
                'starts_at'    => now()->setTime(19, 0),
                'status'       => 'OPEN',
            ]
        );

        // ---- Billing statuses ----
        foreach ([
            ['WAITING',         'À espera',          10],
            ['ACTIVE',          'Ativo',             20],
            ['CHECK_REQUESTED', 'Conta pedida',      30],
            ['PARTIALLY_PAID',  'Parcialmente pago', 40],
            ['CLOSED',          'Fechado',           50],
        ] as [$code, $name, $sort]) {
            BillingStatus::updateOrCreate(
                ['code' => $code],
                ['display_name' => $name, 'sort_order' => $sort, 'is_active' => true]
            );
        }
        $waiting = BillingStatus::where('code', 'WAITING')->first();
        $active  = BillingStatus::where('code', 'ACTIVE')->first();

        // ---- Layout: 2 sections, 2 rows each, 20 seats per row, 10 pairs per row ----
        foreach ([['A', 'Sala A'], ['B', 'Sala B']] as $idx => [$code, $name]) {
            $section = Section::updateOrCreate(
                ['venue_id' => $venue->id, 'section_code' => $code],
                ['name' => $name, 'sort_order' => $idx + 1, 'is_active' => true]
            );

            foreach (['1', '2'] as $rIdx => $rowCode) {
                $row = Row::updateOrCreate(
                    ['section_id' => $section->id, 'row_code' => $rowCode],
                    ['sort_order' => (int) $rowCode, 'is_active' => true]
                );

                // 20 seats
                $seatIds = [];
                for ($n = 1; $n <= 20; $n++) {
                    $seat = Seat::updateOrCreate(
                        ['row_id' => $row->id, 'seat_number' => $n],
                        ['sort_order' => $n, 'is_active' => true]
                    );
                    $seatIds[$n] = $seat->id;
                }
                // 10 pairs (1+2, 3+4, ...)
                for ($p = 1; $p <= 10; $p++) {
                    $a = $seatIds[($p * 2) - 1];
                    $b = $seatIds[$p * 2];
                    SeatPair::updateOrCreate(
                        ['row_id' => $row->id, 'pair_sequence' => $p],
                        ['seat_a_id' => $a, 'seat_b_id' => $b, 'is_active' => true]
                    );
                }
            }
        }

        // ---- Menu ----
        $kitchen = MenuCategory::updateOrCreate(
            ['code' => 'MAIN'],
            ['display_name' => 'Pratos principais', 'route_type' => 'KITCHEN', 'sort_order' => 10, 'is_active' => true]
        );
        $starter = MenuCategory::updateOrCreate(
            ['code' => 'STARTER'],
            ['display_name' => 'Entradas', 'route_type' => 'KITCHEN', 'sort_order' => 5, 'is_active' => true]
        );
        $bar = MenuCategory::updateOrCreate(
            ['code' => 'BAR'],
            ['display_name' => 'Bar', 'route_type' => 'BAR', 'sort_order' => 20, 'is_active' => true]
        );
        $dessert = MenuCategory::updateOrCreate(
            ['code' => 'DESSERT'],
            ['display_name' => 'Sobremesas', 'route_type' => 'KITCHEN', 'sort_order' => 30, 'is_active' => true]
        );

        $menu = [
            [$starter, 'Sopa do dia', 4.50],
            [$starter, 'Salada mista', 6.00],
            [$kitchen, 'Bacalhau à brás', 14.50],
            [$kitchen, 'Bitoque', 12.00],
            [$kitchen, 'Polvo à lagareiro', 18.00],
            [$bar,     'Água 50cl', 1.50],
            [$bar,     'Cerveja imperial', 2.00],
            [$bar,     'Vinho tinto - copo', 2.50],
            [$bar,     'Café', 1.20],
            [$dessert, 'Pudim flan', 3.50],
            [$dessert, 'Arroz doce', 3.00],
        ];
        foreach ($menu as [$cat, $name, $price]) {
            MenuItem::updateOrCreate(
                ['display_name' => $name],
                [
                    'menu_category_id' => $cat->id,
                    'unit_price'       => $price,
                    'is_active'        => true,
                ]
            );
        }

        // ---- Printers ----
        $kitchenPrinter = Printer::updateOrCreate(
            ['name' => 'Cozinha LAN'],
            ['printer_type' => 'KITCHEN', 'connection_type' => 'LAN', 'address' => '192.168.1.50', 'port' => 9100, 'is_active' => true, 'health_status' => 'UNKNOWN']
        );
        $barPrinter = Printer::updateOrCreate(
            ['name' => 'Bar LAN'],
            ['printer_type' => 'BAR', 'connection_type' => 'LAN', 'address' => '192.168.1.51', 'port' => 9100, 'is_active' => true, 'health_status' => 'UNKNOWN']
        );
        $billPrinter = Printer::updateOrCreate(
            ['name' => 'Caixa 1'],
            ['printer_type' => 'BILL', 'connection_type' => 'LAN', 'address' => '192.168.1.60', 'port' => 9100, 'is_active' => true, 'health_status' => 'UNKNOWN']
        );

        // ---- Printer routes ----
        PrinterRoute::updateOrCreate(
            ['venue_id' => $venue->id, 'document_type' => 'PRODUCTION_TICKET', 'fulfillment_route' => 'KITCHEN'],
            ['printer_id' => $kitchenPrinter->id, 'is_active' => true]
        );
        PrinterRoute::updateOrCreate(
            ['venue_id' => $venue->id, 'document_type' => 'PRODUCTION_TICKET', 'fulfillment_route' => 'BAR'],
            ['printer_id' => $barPrinter->id, 'is_active' => true]
        );
        PrinterRoute::updateOrCreate(
            ['venue_id' => $venue->id, 'document_type' => 'VOID_SLIP', 'fulfillment_route' => 'KITCHEN'],
            ['printer_id' => $kitchenPrinter->id, 'is_active' => true]
        );
        PrinterRoute::updateOrCreate(
            ['venue_id' => $venue->id, 'document_type' => 'VOID_SLIP', 'fulfillment_route' => 'BAR'],
            ['printer_id' => $barPrinter->id, 'is_active' => true]
        );

        // ---- Cashier printer assignment ----
        CashierPrinterAssignment::updateOrCreate(
            ['user_id' => $cashier->id, 'printer_id' => $billPrinter->id],
            ['is_active' => true]
        );

        // ---- Sample open billing group spanning two zones ----
        $rowA1 = Row::whereHas('section', fn ($q) => $q->where('section_code', 'A'))->where('row_code', '1')->first();
        $rowA2 = Row::whereHas('section', fn ($q) => $q->where('section_code', 'A'))->where('row_code', '2')->first();

        $group = BillingGroup::updateOrCreate(
            ['service_session_id' => $session->id, 'display_code' => 'G-001'],
            [
                'billing_status_id' => $active->id,
                'cover_count'       => 6,
                'opened_by_user_id' => $server->id,
                'opened_at'         => now(),
                'is_closed'         => false,
                'version_number'    => 1,
            ]
        );

        OccupiedZone::updateOrCreate(
            ['billing_group_id' => $group->id, 'row_id' => $rowA1->id, 'start_seat_pair_sequence' => 1, 'end_seat_pair_sequence' => 2],
            [
                'default_delivery_mode' => 'CENTER',
                'delivery_center_label' => 'Sala A · linha 1 · centro',
                'opened_at'             => now(),
                'is_open'               => true,
                'created_by_user_id'    => $server->id,
            ]
        );
        OccupiedZone::updateOrCreate(
            ['billing_group_id' => $group->id, 'row_id' => $rowA2->id, 'start_seat_pair_sequence' => 3, 'end_seat_pair_sequence' => 3],
            [
                'default_delivery_mode' => 'CENTER',
                'delivery_center_label' => 'Sala A · linha 2 · centro',
                'opened_at'             => now(),
                'is_open'               => true,
                'created_by_user_id'    => $server->id,
            ]
        );

        // ---- Translations (complete operational UI set) ----
        $translations = [
            // General
            ['pt-PT', 'app', 'name', 'Serveo'],
            ['pt-PT', 'app', 'cancel', 'Cancelar'],
            ['pt-PT', 'app', 'back', 'Voltar'],
            ['pt-PT', 'app', 'save', 'Guardar'],
            ['pt-PT', 'app', 'delete', 'Eliminar'],
            ['pt-PT', 'app', 'edit', 'Editar'],
            ['pt-PT', 'app', 'create', 'Criar'],
            ['pt-PT', 'app', 'search', 'Pesquisar'],
            ['pt-PT', 'app', 'actions', 'Ações'],
            ['pt-PT', 'app', 'status', 'Estado'],
            ['pt-PT', 'app', 'total', 'Total'],
            ['pt-PT', 'app', 'balance', 'Saldo'],
            ['pt-PT', 'app', 'notes', 'Notas'],
            ['pt-PT', 'app', 'yes', 'Sim'],
            ['pt-PT', 'app', 'no', 'Não'],
            ['pt-PT', 'app', 'open', 'Aberto'],
            ['pt-PT', 'app', 'closed', 'Fechado'],
            ['pt-PT', 'app', 'waiting', 'À espera'],
            ['pt-PT', 'app', 'active', 'Ativo'],
            ['pt-PT', 'app', 'empty', 'Vazio'],
            ['pt-PT', 'app', 'none', 'Nenhum'],
            ['pt-PT', 'app', 'error', 'Erro'],
            ['pt-PT', 'app', 'success', 'Sucesso'],
            ['pt-PT', 'app', 'warning', 'Aviso'],

            // Floor
            ['pt-PT', 'floor', 'title', 'Plano de sala'],
            ['pt-PT', 'floor', 'open_group', 'Abrir novo grupo'],
            ['pt-PT', 'floor', 'no_session', 'Não existe nenhuma sessão de serviço aberta. Crie uma sessão em Configuração → Sessões de serviço antes de operar o salão.'],
            ['pt-PT', 'floor', 'session_start', 'Início'],
            ['pt-PT', 'floor', 'open_groups', 'Grupos abertos'],
            ['pt-PT', 'floor', 'no_open_groups', 'Nenhum grupo aberto nesta sessão.'],
            ['pt-PT', 'floor', 'no_zones', 'Sem zonas atribuídas'],
            ['pt-PT', 'floor', 'row', 'Fila'],
            ['pt-PT', 'floor', 'free', 'Livre'],
            ['pt-PT', 'floor', 'busy', 'Ocupado'],

            // Order
            ['pt-PT', 'order', 'title', 'Pedido'],
            ['pt-PT', 'order', 'new_order', 'Novo pedido'],
            ['pt-PT', 'order', 'submit', 'Enviar pedido'],
            ['pt-PT', 'order', 'cart', 'Carrinho'],
            ['pt-PT', 'order', 'empty_cart', 'Nenhum item adicionado.'],
            ['pt-PT', 'order', 'remove', 'remover'],
            ['pt-PT', 'order', 'delivery_zone', 'Zona de entrega'],
            ['pt-PT', 'order', 'no_specific_zone', '— sem zona específica —'],
            ['pt-PT', 'order', 'seat_pair', 'Par de lugar (opcional)'],
            ['pt-PT', 'order', 'center_of_zone', '— centro da zona —'],
            ['pt-PT', 'order', 'order_notes', 'Notas'],
            ['pt-PT', 'order', 'cancel_back', 'Cancelar e voltar ao grupo'],
            ['pt-PT', 'order', 'order_sent', 'Pedido enviado para produção'],
            ['pt-PT', 'order', 'cart_empty_warning', 'Carrinho vazio'],
            ['pt-PT', 'order', 'order_failed', 'Falha ao submeter pedido'],

            // Billing / Group
            ['pt-PT', 'billing', 'group_title', 'Grupo'],
            ['pt-PT', 'billing', 'assign_zone', 'Atribuir zona'],
            ['pt-PT', 'billing', 'start_pair', 'Par inicial'],
            ['pt-PT', 'billing', 'end_pair', 'Par final'],
            ['pt-PT', 'billing', 'delivery_label', 'Etiqueta entrega'],
            ['pt-PT', 'billing', 'new_order', 'Novo pedido'],
            ['pt-PT', 'billing', 'print_bill', 'Imprimir conta'],
            ['pt-PT', 'billing', 'reopen_group', 'Reabrir grupo'],
            ['pt-PT', 'billing', 'status', 'Estado'],
            ['pt-PT', 'billing', 'total_to_pay', 'Total a pagar'],
            ['pt-PT', 'billing', 'paid', 'Pago'],
            ['pt-PT', 'billing', 'open_balance', 'Saldo em aberto'],
            ['pt-PT', 'billing', 'occupied_zones', 'Zonas ocupadas'],
            ['pt-PT', 'billing', 'no_zones', 'Sem zonas atribuídas.'],
            ['pt-PT', 'billing', 'released', 'libertada'],
            ['pt-PT', 'billing', 'release_zone', 'Libertar'],
            ['pt-PT', 'billing', 'release_confirm', 'Libertar zona'],
            ['pt-PT', 'billing', 'orders', 'Pedidos'],
            ['pt-PT', 'billing', 'no_orders', 'Sem pedidos.'],
            ['pt-PT', 'billing', 'item', 'Item'],
            ['pt-PT', 'billing', 'qty', 'Qtd'],
            ['pt-PT', 'billing', 'route', 'Rota'],
            ['pt-PT', 'billing', 'delivery', 'Entrega'],
            ['pt-PT', 'billing', 'subtotal', 'Subtotal'],
            ['pt-PT', 'billing', 'documents_payments', 'Documentos & pagamentos'],
            ['pt-PT', 'billing', 'printed_bills', 'Contas impressas'],
            ['pt-PT', 'billing', 'no_bills', 'Sem contas impressas.'],
            ['pt-PT', 'billing', 'reprint', 'reimpressão'],
            ['pt-PT', 'billing', 'payments', 'Pagamentos'],
            ['pt-PT', 'billing', 'no_payments', 'Sem pagamentos.'],
            ['pt-PT', 'billing', 'zone_assigned', 'Zona atribuída'],
            ['pt-PT', 'billing', 'zone_overlap', 'Sobreposição de zonas'],
            ['pt-PT', 'billing', 'zone_error', 'Erro ao atribuir zona'],
            ['pt-PT', 'billing', 'zone_released', 'Zona libertada'],
            ['pt-PT', 'billing', 'bill_sent', 'Conta enviada para impressão'],
            ['pt-PT', 'billing', 'group_reopened', 'Grupo reaberto'],
            ['pt-PT', 'billing', 'closed_group', 'Grupo fechado.'],
            ['pt-PT', 'billing', 'bill_error', 'Erro'],

            // Cashier
            ['pt-PT', 'cashier', 'title', 'Caixa'],
            ['pt-PT', 'cashier', 'no_session', 'Não existe sessão de serviço aberta.'],
            ['pt-PT', 'cashier', 'no_groups', 'Nenhum grupo a apresentar.'],
            ['pt-PT', 'cashier', 'group', 'Grupo'],
            ['pt-PT', 'cashier', 'zones', 'Zonas'],
            ['pt-PT', 'cashier', 'total', 'Total'],
            ['pt-PT', 'cashier', 'paid', 'Pago'],
            ['pt-PT', 'cashier', 'balance', 'Saldo'],
            ['pt-PT', 'cashier', 'actions', 'Ações'],
            ['pt-PT', 'cashier', 'print_bill', 'Imprimir conta'],
            ['pt-PT', 'cashier', 'reprint', 'Reimprimir'],
            ['pt-PT', 'cashier', 'reopen', 'Reabrir'],
            ['pt-PT', 'cashier', 'reopen_confirm', 'Reabrir grupo'],
            ['pt-PT', 'cashier', 'record_payment', 'Registar pagamento'],
            ['pt-PT', 'cashier', 'payment_group', 'Grupo'],
            ['pt-PT', 'cashier', 'payment_amount', 'Valor (EUR)'],
            ['pt-PT', 'cashier', 'payment_label', 'Forma de pagamento'],
            ['pt-PT', 'cashier', 'payment_default', 'Numerário'],
            ['pt-PT', 'cashier', 'payment_recorded', 'Pagamento registado'],
            ['pt-PT', 'cashier', 'show_closed', 'Mostrar fechados'],
            ['pt-PT', 'cashier', 'hide_closed', 'Ocultar fechados'],
            ['pt-PT', 'cashier', 'bill_sent', 'Conta enviada para impressora'],
            ['pt-PT', 'cashier', 'no_bill_reprint', 'Sem conta para reimprimir'],
            ['pt-PT', 'cashier', 'reprint_sent', 'Reimpressão enviada'],

            // Ticket labels
            ['pt-PT', 'ticket', 'void', 'ANULAÇÃO'],
            ['pt-PT', 'ticket', 'reprint', 'REIMPRESSÃO'],
            ['pt-PT', 'ticket', 'group', 'Grupo'],
            ['pt-PT', 'ticket', 'zone', 'Zona'],
            ['pt-PT', 'ticket', 'delivery', 'Entrega'],
            ['pt-PT', 'ticket', 'time', 'Hora'],
            ['pt-PT', 'ticket', 'ticket_num', 'Ticket'],
            ['pt-PT', 'ticket', 'internal_bill', 'CONTA INTERNA'],
            ['pt-PT', 'ticket', 'document', 'Documento'],
            ['pt-PT', 'ticket', 'subtotal', 'SUBTOTAL'],
            ['pt-PT', 'ticket', 'total', 'TOTAL'],
            ['pt-PT', 'ticket', 'paid', 'Pago'],
            ['pt-PT', 'ticket', 'due', 'Em dívida'],
            ['pt-PT', 'ticket', 'no_fiscal', 'Documento interno - sem valor fiscal'],

            ['en-US', 'ticket', 'void', 'VOID'],
            ['en-US', 'ticket', 'reprint', 'REPRINT'],
            ['en-US', 'ticket', 'group', 'Group'],
            ['en-US', 'ticket', 'zone', 'Zone'],
            ['en-US', 'ticket', 'delivery', 'Delivery'],
            ['en-US', 'ticket', 'time', 'Time'],
            ['en-US', 'ticket', 'ticket_num', 'Ticket'],
            ['en-US', 'ticket', 'internal_bill', 'INTERNAL BILL'],
            ['en-US', 'ticket', 'document', 'Document'],
            ['en-US', 'ticket', 'subtotal', 'SUBTOTAL'],
            ['en-US', 'ticket', 'total', 'TOTAL'],
            ['en-US', 'ticket', 'paid', 'Paid'],
            ['en-US', 'ticket', 'due', 'Due'],
            ['en-US', 'ticket', 'no_fiscal', 'Internal document - no fiscal value'],
        ];
        foreach ($translations as [$lang, $ns, $key, $val]) {
            TranslationKey::updateOrCreate(
                ['language_code' => $lang, 'translation_namespace' => $ns, 'translation_key' => $key],
                ['translation_value' => $val, 'is_active' => true]
            );
        }
    }
}

<?php
/* --------------------------------------------------------------
   EverysizeCheckoutProtocolController.inc.php

   Module protocol page (admin.php?do=EverysizeCheckoutProtocol): the
   import/export/cron entries of everysize_checkout_protocol, new first,
   filterable by state (all/ok/error), 50 per page. created_at is stored
   with MySQL NOW() and the DB runs UTC (time_zone=SYSTEM) while the
   shop renders in its own time zone — so UTC is converted to the shop
   time zone here, the same way the order box converts API times
   (date_default_timezone_get() is the shop time zone).
   --------------------------------------------------------------*/

require_once __DIR__ . '/../../../src/autoload.php';

class EverysizeCheckoutProtocolController extends AdminHttpViewController
{
    private const PER_PAGE = 50;

    private const STATES = ['all', 'ok', 'error'];


    public function actionDefault(): AdminLayoutHttpControllerResponse
    {
        $text = MainFactory::create('LanguageTextManager', 'everysizecheckout', (int) ($_SESSION['languages_id'] ?? 0));
        $titleText = (string) $text->get_text('everysizecheckout_protocol_title');
        $title = new NonEmptyStringType($titleText !== '' ? $titleText : 'everysize Protokoll');
        $template = $this->getTemplateFile('Everysize/Checkout/Admin/Html/everysize_checkout_protocol.html');

        $state = (string) ($_GET['state'] ?? 'all');
        if (!in_array($state, self::STATES, true)) {
            $state = 'all';
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = $this->totalEntries($state);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        if ($page > $pages) {
            $page = $pages;
        }

        $baseUrl = 'admin.php?do=EverysizeCheckoutProtocol';
        $url = static function (string $state, int $page) use ($baseUrl): string {
            return $baseUrl . '&state=' . $state . '&page=' . $page;
        };

        $data = MainFactory::create('KeyValueCollection', [
            'pageToken' => $_SESSION['coo_page_token']->generate_token(),
            'entries'   => $this->entries($state, $page),
            'state'     => $state,
            'page'      => $page,
            'pages'     => $pages,
            'total'     => $total,
            'urlAll'    => $url('all', 1),
            'urlOk'     => $url('ok', 1),
            'urlError'  => $url('error', 1),
            'urlPrev'   => $url($state, max(1, $page - 1)),
            'urlNext'   => $url($state, min($pages, $page + 1)),
            'hasPrev'   => $page > 1 ? '1' : '',
            'hasNext'   => $page < $pages ? '1' : '',
            'pagerText' => sprintf((string) ($text->get_text('everysizecheckout_protocol_pager') ?: 'Page %1$d of %2$d'),
                                   $page, $pages),
        ]);

        return MainFactory::create('AdminLayoutHttpControllerResponse', $title, $template, $data);
    }


    private function totalEntries(string $state): int
    {
        $sql = 'SELECT COUNT(*) FROM everysize_checkout_protocol';
        $sql .= $state === 'all' ? '' : " WHERE state = '" . $state . "'";
        $total = EverysizeCheckoutFactory::db()->fetchValue($sql);

        return $total === null ? 0 : (int) $total;
    }


    /**
     * $state is whitelisted ('all'|'ok'|'error') and the page comes from
     * an int, so the inline LIMIT/OFFSET/WHERE carry no input.
     *
     * @return list<array{TIME: string, TITLE: string, MESSAGE: string, STATE: string}>
     */
    private function entries(string $state, int $page): array
    {
        $where = $state === 'all' ? '' : " WHERE state = '" . $state . "'";
        $offset = ($page - 1) * self::PER_PAGE;
        $rows = EverysizeCheckoutFactory::db()->fetchRows(
            'SELECT created_at AS TIME, title AS TITLE, message AS MESSAGE, state AS STATE
             FROM everysize_checkout_protocol' . $where . '
             ORDER BY id DESC LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset
        );
        foreach ($rows as &$row) {
            // DB stores UTC (MySQL NOW(), time_zone=SYSTEM) — render as shop time.
            $row['TIME'] = (string) $row['TIME'] !== ''
                ? (new DateTimeImmutable((string) $row['TIME'], new DateTimeZone('UTC')))
                    ->setTimezone(new DateTimeZone(date_default_timezone_get()))
                    ->format('d.m.Y H:i:s') : '';
            $row['TITLE'] = (string) $row['TITLE'];
            $row['MESSAGE'] = (string) $row['MESSAGE'];
            $row['STATE'] = (string) $row['STATE'];
        }

        return $rows;
    }
}

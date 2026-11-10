<?php

declare(strict_types=1);

/*
 | Shared chrome for the legal pages (/privacy, /termini): draft notice, version and table
 | of contents labels. The actual content lives in legal_privacy.php and legal_terms.php.
 |
 | ⚠️ DRAFT — the legal texts in this group (here and in legal_privacy/legal_terms) are a
 | first technical draft and carry NO legal value until reviewed and approved by the data
 | controller (kiba.studio, Alessio Sada) and, where appropriate, by legal counsel.
 */

return [
    'draft' => [
        'label' => 'Notice',
        'title' => 'Draft pending review',
        'body' => 'This document is a working draft and does not yet have legal value: it must be reviewed and approved by the data controller before final publication.',
    ],

    'toc' => 'On this page',
    'updated' => 'Last updated: :date',
    'version' => 'Version :version',
];

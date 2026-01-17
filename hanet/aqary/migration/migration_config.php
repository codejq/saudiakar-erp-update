<?php
/**
 * Database Migration Configuration
 *
 * Configuration file for migrating data from old database (aqary) to new database (aqary_utf8)
 * Handles encoding conversion from Windows-1256 to UTF-8/UTF8MB4
 */

// Include database connection details
require_once('config.php');

// Migration Settings
define('BATCH_SIZE', 100); // Number of rows to process per batch
define('LOG_FILE', __DIR__ . '/migration_log_' . date('Y-m-d_H-i-s') . '.txt');
define('DRY_RUN', false); // Set to true to test without writing to new database

// Database Connection Settings (from config.php)
$old_db_config = [
    'host' => $old_mysql_server,
    'port' => $old_mysql_port,
    'user' => $old_mysql_user,
    'password' => $old_mysql_password,
    'database' => $old_mysql_database,
    'charset' => 'latin1' // Old database uses latin1
];

$new_db_config = [
    'host' => $new_mysql_server,
    'port' => $new_mysql_port,
    'user' => $new_mysql_user,
    'password' => $new_mysql_password,
    'database' => $new_mysql_database,
    'charset' => 'utf8mb4' // New database uses utf8mb4
];

/**
 * Table Migration Order
 *
 * Tables are listed in dependency order to handle foreign key relationships
 * Tier 1: Independent tables (no foreign keys)
 * Tier 2: Parent tables
 * Tier 3: Child tables with dependencies
 * Tier 4: Other tables
 */
$migration_tables = [
    // Tier 1 - Independent Tables
    'city',
    'aqartype',
    'aqarsubtype',
    'amlakowner',
    'mostagereen',
    'custummer',
    'controltable',
    'bankacounts',
    'countries',
    'departments_name',
    'usergroups',
    'screens',
    'masder',
    'malek',
    'masrofattakweed',

    // Tier 2 - Parent Tables
    'amlak',
    'arady',
    'emara',
    'mokhatat',
    'masader',
    'department_field',
    'department_query',

    // Tier 3 - Child Tables
    'amlakdetails',
    'egar',
    'aksat',
    'eradat',
    'masrofat',
    'aksat_payment_history',
    'dep_query_field',
    'ugroupscreen',
    'sanad',
    'sanadsarf',

    // Tier 4 - Other Tables
    'document',
    'alarmclock',
    'custumreport',
    'custum_fields',
    'userreport',
    'print_template',
    'reportfield',
    'reports',
    'sandat',
    'settings',
    'systemactions',
    'user',
    'userslog',
    'vila',
    'letters',
    'payment_voucher',
    'recive_voucher',
];




/**
 * Text Column Definitions
 *
 * Defines which columns need encoding conversion for each table
 * Format: 'table_name' => ['column1', 'column2', ...]
 *
 * These columns will be converted from Windows-1256 to UTF-8
 */
$text_columns = [
    'city' => ['cityname'],
    'aqartype' => ['aqartypename', 'note'],
    'aqarsubtype' => ['aqarsubtypename', 'aqartype'],
    'amlakowner' => ['ownername', 'mobile', 'phone', 'owneraddress', 'email', 'hawiarakam', 'hawiaplace', 'hawiadate', 'hawiatype', 'gensia', 'extra1', 'extra2', 'extra3', 'extra4'],
    'mostagereen' => ['mostagername', 'mostageraddress', 'mostageridnumber', 'mostagergensia', 'mostagertel', 'workaddress', 'kafelname', 'kafeladdress', 'kafelphone', 'work', 'note', 'mostageremail', 'mostagerwebsite', 'mostageragent', 'mostageridplace', 'mostageriddate', 'mostageridtype', 'mostagervatnumber'],
    'custummer' => ['custummertel', 'custummerfax', 'custumeremail', 'custummeraddres', 'descrption', 'formuid'],
    'amlak' => ['aqartype', 'aqarname', 'aqaraddress', 'ownername', 'mobile', 'phone', 'owneraddress', 'persentage', 'email', 'description', 'hawiarakam', 'gensia', 'hawiaplace', 'hawiadate', 'hawiatype', 'addtameento', 'addwaterto', 'externaltype', 'users', 'owner_vat_number', 'bankname', 'acountnumber', 'acountholder', 'iban'],
    'amlakdetails' => ['note', 'rakam', 'hnt_dll_type', 'formuid', 'images', 'externaltype'],
    'egar' => ['aqaraddress', 'depositeamount', 'aqartype', 'rakam3akd', 'txt3akd', 'payedamount', 'txtcash', 'date3akd', 'duration', 'txtcash_word', 'egartahseldate', 'formuid', 'attachedimages', 'elect_reading', 'notes', 'elect'],
    'aksat' => ['kastname', 'dof3at_tafsel', 'formuid'],
    'arady' => ['block', 'ardrakam', 'hedod', 'ardtype', 'hay', 'note', 'al3ard', 'tol', 'malekbayan1', 'malekbayan2', 'malekbayan3', 'malekbayan4', 'malekbayan5', 'malekbayan6', 'malekbayan7', 'malekbayan8', 'malekbayan9', 'malekbayan10', 'oldmalekbayan1', 'oldmalekbayan2', 'oldmalekbayan3', 'oldmalekbayan4', 'oldmalekbayan5', 'oldmalekbayan6', 'oldmalekbayan7', 'oldmalekbayan8', 'oldmalekbayan9', 'oldmalekbayan10', 'secretinfo', 'statusx', 'reversedby', 'reversedfor', 'soldto', 'meterprice', 'tamweelmethod', 'daf3method', 'khdma', 'share3f', 'share3', 'khazina', 'dorg', 'raf', 'paperfile', 'googlexyz', 'enbleslideshow', 'ploypath', 'polypath'],
    'document' => ['documenttitle', 'document'],
    'emara' => ['emararakam', 'hay', 'block', 'saletype', 'address', 'note', 'som', 'emaratype', 'mobasher', 'hedod', 'dakhl', 'malekbayan1', 'malekbayan2', 'malekbayan3', 'malekbayan4', 'malekbayan5', 'malekbayan6', 'malekbayan7', 'malekbayan8', 'malekbayan9', 'malekbayan10', 'oldmalekbayan1', 'oldmalekbayan2', 'oldmalekbayan3', 'oldmalekbayan4', 'oldmalekbayan5', 'oldmalekbayan6', 'oldmalekbayan7', 'oldmalekbayan8', 'oldmalekbayan9', 'oldmalekbayan10', 'khazina', 'dorg', 'raf', 'paperfile', 'benamesaha', 'aqarage', 'madakhel', 'tol', 'al3ard', 'secretinfo', 'share3', 'khdma', 'tamweelmethod', 'daf3method', 'statusx', 'reversedfor', 'soldto', 'reversedby', 'kabou', 'moager', 'emaradat', 'modat3aked', 'egarshaga', 'egarma3rad', 'googlexyz', 'enbleslideshow', 'formuid', 'polypath'],
    'controltable' => ['t_fieldname', 't_tablename', 't_caption', 't_caption_en', 't_fieldtype', 't_t'],
    'bankacounts' => ['bankname', 'bankbranch', 'acountnumber', 'acountholder', 'swift', 'ownerid'],
    'alarmclock' => ['message', 'note', 'mp3filename'],
    'custumreport' => ['reportname', 'reportorderby', 'reportdaesc', 'userid', 'reporttype', 'reportdefault', 'sqlfeilds', 'formuid', 'sumfields'],
    'countries' => ['code', 'country', 'formuid'],
    'departments_name' => ['dep_name', 'dep_en_name', 'dep_image', 'dep_image_small', 'dep_isactive', 'dep_table_name', 'dep_lastmodified', 'dep_isdeleted', 'formuid'],
    'department_field' => ['fld_name', 'fld_en_name', 'fld_table_name', 'fld_isactive', 'fld_required', 'fld_values', 'fld_type', 'formuid'],
    'department_query' => ['qry_name', 'qry_sql', 'qry_image', 'qry_fields', 'qry_en_name', 'qry_isdeleted', 'qry_isactive', 'qry_order', 'qry_lastmodified', 'formuid'],
    'dep_query_field' => ['sqlfildname', 'sqloprator', 'sqllogic', 'inputfrom', 'isrequired', 'datavalue', 'sqlfild_ar_name', 'query_id', 'formuid'],
    'mokhatat' => ['mokhatatname', 'formuid'],
    'screens' => ['screen_id', 'screen_name', 'screen_isactive', 'formuid'],
    'ugroupscreen' => ['formuid'],
    'usergroups' => ['groupname', 'description', 'formuid'],
    'userreport' => ['userid', 'reportid', 'reporttype', 'tt', 'formuid'],
    'custum_fields' => ['formuid'],
    'vila' => ['vilarakam', 'hay', 'block', 'saletype', 'address', 'note', 'som', 'vilatype', 'mobasher', 'hedod', 'dakhl', 'malekbayan1', 'malekbayan2', 'malekbayan3', 'malekbayan4', 'malekbayan5', 'malekbayan6', 'malekbayan7', 'malekbayan8', 'malekbayan9', 'malekbayan10', 'oldmalekbayan1', 'oldmalekbayan2', 'oldmalekbayan3', 'oldmalekbayan4', 'oldmalekbayan5', 'oldmalekbayan6', 'oldmalekbayan7', 'oldmalekbayan8', 'oldmalekbayan9', 'oldmalekbayan10', 'khazina', 'dorg', 'raf', 'paperfile', 'reversedfor', 'soldto', 'statusx', 'daf3method', 'tamweelmethod', 'khdma', 'share3', 'secretinfo', 'tol', 'al3ard', 'madakhel', 'benamesaha', 'aqarage', 'reversedby', 'nomghorf', 'salatnumber', 'googlexyz', 'enbleslideshow', 'formuid', 'polypath'],
    'sanad' => ['mostagername', 'recivername', 'mybyan', 'sanadrakam', 'cheqnumber', 'cheqon', 'cheqdate1', 'kastid', 'mostagerid', 'period', 'formuid', 'attachedimages', 'notes', 'mostagervatnumber', 'suppliername', 'suppliervatnumber'],
    'sanadsarf' => ['bayan', 'note', 'mostafeed', 'mowazaf', 'extradata', 'checkrakam', 'checkdate', 'checkbank', 'tasfia', 'formuid', 'masrofatarray', 'attachedimages', 'notes'],
    'payment_voucher' => ['halala', 'sanaddate', 'receivedfrom', 'checknumber', 'bankname', 'checknumberen', 'ddd', 'mmm', 'yyy', 'dddd', 'mmmm', 'yyyy', 'banknameen', 'thrsumof', 'dueto', 'fmanger', 'accountant', 'casher', 'receivedby', 'formuid', 'notes'],
    'recive_voucher' => ['halala', 'sanaddate', 'receivedfrom', 'checknumber', 'bankname', 'checknumberen', 'ddd', 'mmm', 'yyy', 'dddd', 'mmmm', 'yyyy', 'banknameen', 'thrsumof', 'dueto', 'fmanger', 'accountant', 'casher', 'receivedby', 'formuid', 'notes', 'mostagervatnumber', 'suppliername', 'suppliervatnumber'],
    'masrofattakweed' => ['masrofkind', 'formuid'],
    'masder' => ['masdertel', 'masderfax', 'masderemail', 'masderaddress', 'masdername', 'mtype', 'mtemp', 'addationalinfo', 'masderphone', 'formuid'],
    'malek' => ['malekbayan1', 'malekbayan2', 'malekbayan3', 'malekbayan4', 'malekbayan5', 'malekbayan6', 'malekbayan7', 'malekbayan8', 'malekbayan9', 'malekbayan10', 'oldmalekbayan1', 'oldmalekbayan2', 'oldmalekbayan3', 'oldmalekbayan4', 'oldmalekbayan5', 'oldmalekbayan6', 'oldmalekbayan7', 'oldmalekbayan8', 'oldmalekbayan9', 'oldmalekbayan10', 'formuid'],
    'letters' => ['letterdata', 'letterlastupdated', 'lettertitle', 'formuid'],
    'eradat' => ['mostagername', 'recivername', 'pymentdesription', 'sanadrakam', 'mostagerid', 'tasfia', 'formuid'],
    'masrofat' => ['payedby', 'payeddue', 'tasfia', 'formuid'],
    'user' => ['username', 'password', 'name', 'mainpage', 'seeothermasder', 'bgimage', 'googlex', 'googley', 'googlezoom', 'countryname', 'cityname', 'mokhatatname', 'formuid'],
];

/**
 * Column Exclusions
 *
 * Columns that exist in new database but not in old database
 * These will be populated with default values or NULL
 * Format: 'table_name' => ['column1', 'column2', ...]
 */
$excluded_columns = [
    'aksat' => ['status', 'paid_date', 'amount_paid', 'amount_remaining', 'late_fee', 'late_fee_waived', 'grace_period_days', 'effective_due_date', 'created_at', 'updated_at', 'services'],
    'amlak' => ['owner_vat_number', 'bankname', 'acountnumber', 'acountholder', 'iban'],
    'amlakowner' => ['hawiaplace', 'hawiadate', 'hawiatype', 'owner_vat_number'],
    // Add more as needed when you discover schema differences
];

/**
 * Special Handling Rules
 *
 * Custom rules for specific tables that need special processing
 */
$special_handling = [
    'aksat' => [
        'default_values' => [
            'status' => 'pending',
            'amount_paid' => 0.00,
            'amount_remaining' => 0.00,
            'late_fee' => 0.00,
            'late_fee_waived' => 0,
            'grace_period_days' => 0,
            'created_at' => 0,
            'updated_at' => 0,
            'services' => 0.00,
        ],
        'calculated_fields' => [
            'amount_remaining' => 'kastvalue - dof3at', // Calculate remaining amount
        ]
    ],
];

/**
 * Tables to Skip
 *
 * Tables that exist in new database but should not be migrated from old database
 * (e.g., new system tables that don't have equivalents in old database)
 */
$skip_tables = [
    'acc_audit_log',
    'acc_chart_of_accounts',
    'acc_customers',
    'acc_fiscal_years',
    'acc_invoices',
    'acc_invoice_items',
    'acc_journal_entries',
    'acc_journal_entry_lines',
    'acc_suppliers',
    'acc_system_settings',
    'ai_cache',
    'ai_generated_content',
    'ai_usage_log',
    'ai_user_preferences',
    'migration_progress', // Don't migrate the tracking table itself
];

/**
 * Encoding Settings
 */
$encoding_settings = [
    'source_encoding' => 'windows-1256',
    'target_encoding' => 'UTF-8',
    'iconv_options' => '//IGNORE', // Ignore invalid characters
];

/**
 * Performance Settings
 */
$performance_settings = [
    'batch_size' => BATCH_SIZE,
    'use_transactions' => true,
    'disable_foreign_keys' => true,
    'memory_limit' => '2048M',
    'execution_time_limit' => 0, // Unlimited
];

?>

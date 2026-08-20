<?php
/**
 * Common language constants file
 */

$MESS['uplab.tilda_SETTINGS_TAB_NAME'] = 'API access settings';
$MESS['uplab.tilda_PUBLIC_KEY'] = 'Public key:';
$MESS['uplab.tilda_SECRET_KEY'] = 'Secret key:';
$MESS['uplab.tilda_SETTINGS_WARNING'] = "<b>API interaction is available only on the Business plan.</b><br />To get started, you need to obtain the Public and Secret keys in your Tilda account (Site settings - Export - API Integration).";

$MESS['uplab.tilda_MENU_TITLE'] = 'Tilda integration';
$MESS['uplab.tilda_CLEAR_CACHE_MENU_TITLE'] = 'Clear cache completely';
$MESS['uplab.tilda_CLEAR_CACHE_LIST_MENU_TITLE'] = 'Refresh page list';
$MESS['uplab.tilda_CLEAR_CACHE_CONFIRM'] = 'Delete cache?';
$MESS['uplab.tilda_CLEAR_CACHE_LIST_CONFIRM'] = 'Refresh page list?';
$MESS['uplab.tilda_ADD_NEW_PAGE_MENU_TITLE'] = 'Add page';

$MESS['uplab.tilda_CACHE_CLEARED'] = 'Cache successfully cleared';
$MESS['uplab.tilda_CACHE_LIST_CLEARED'] = 'Page list successfully refreshed';

$MESS['uplab.tilda_PAGE_SELECT'] = 'Page selection';
$MESS['uplab.tilda_SELECT_PROJECT'] = 'Select a project:';
$MESS['uplab.tilda_SELECT_PAGE'] = 'Select a page:';
$MESS['uplab.tilda_NO_TEMPLATE'] = 'Do not output the site template:';
$MESS['uplab.tilda_MOVE_TILDA_ASSETS'] = 'Move Tilda styles and scripts:';
$MESS['uplab.tilda_MOVE_TILDA_ASSETS_NONE'] = 'do not move';
$MESS['uplab.tilda_MOVE_TILDA_ASSETS_HEADEND'] = 'to the end of the head section';
$MESS['uplab.tilda_MOVE_TILDA_ASSETS_BODYEND'] = 'to the end of the body section';

$MESS['uplab.tilda_NO_PROJECTS'] = 'There are no projects (pages), or the Tilda API keys are not specified.';
$MESS['uplab.tilda_NO_KEYS'] = 'Specify keys';

$MESS['uplab.tilda_PAGES_LOADING'] = 'Loading the page list...';
$MESS['uplab.tilda_PAGES_EMPTY'] = 'The project has no pages.';
$MESS['uplab.tilda_PAGES_LOAD_ERROR'] = 'Failed to load the page list.';
$MESS['uplab.tilda_PAGES_LOAD_TIMEOUT'] = 'Tilda did not respond in time. The page list was not loaded.';
$MESS['uplab.tilda_PROJECTS_LOAD_ERROR'] = 'Failed to load the Tilda project list: #MESSAGE#';
$MESS['uplab.tilda_RETRY'] = 'Retry';
$MESS['uplab.tilda_PAGE_NOT_SELECTED'] = 'Select a page.';

$MESS['uplab.tilda_NO_MODULE'] = 'The "Tilda Integration" module (uplab.tilda) is not installed';

$MESS['uplab.tilda_PAGES'] = 'Pages';

$MESS['uplab.tilda_BASE_SETTINGS'] = 'Tilda connection settings';
$MESS['uplab.tilda_API_URL'] = 'Tilda API base URL (default https://api.tildacdn.info/v1/).';
$MESS['uplab.tilda_API_URL_INVALID'] = 'The "Tilda Integration" module (uplab.tilda): the API address in the settings is invalid, using #URL#';
$MESS['uplab.tilda_TIMEOUT'] = 'Overall request time limit across all retry attempts, seconds.';
$MESS['uplab.tilda_CONNECTTIMEOUT'] = 'The number of seconds to wait while trying to connect. Use 0 to wait indefinitely.';
$MESS['uplab.tilda_LIST_CACHE_TTL'] = 'Cache lifetime for project and page lists, seconds. Defines how soon a new Tilda page shows up in the editor (page content is cached separately, for a week).';

$MESS['uplab.tilda_SESSION_EXPIRED'] = 'Your session has expired! Please reload the page.';
$MESS['uplab.tilda_UNKNOWN_ACTION'] = 'Unknown action.';

$MESS['uplab.tilda_LOGGING_TAB_NAME']   = 'Logging';
$MESS['uplab.tilda_LOGGING_SECTION']    = 'Logging settings';
$MESS['uplab.tilda_LOG_ENABLED']        = 'Enable logging:';
$MESS['uplab.tilda_LOG_LEVEL']          = 'Minimum log level:';
$MESS['uplab.tilda_LOG_LEVEL_DEBUG']    = 'Debug — all events (requests, cache, responses)';
$MESS['uplab.tilda_LOG_LEVEL_INFO']     = 'Info — cache writes and above';
$MESS['uplab.tilda_LOG_LEVEL_WARNING']  = 'Warning — warnings and errors';
$MESS['uplab.tilda_LOG_LEVEL_ERROR']    = 'Error — errors only';
$MESS['uplab.tilda_LOG_DIR'] = 'Log directory. Leave empty to write logs into the module directory. A path without a leading slash (upload/tilda_logs) is relative to the site root, with a leading slash (/var/log/tilda) it is an absolute server path:';
$MESS['uplab.tilda_LOG_DIR_LABEL']      = 'Log files are written to:';
$MESS['uplab.tilda_LOG_DIR_EXISTS']     = 'directory exists';
$MESS['uplab.tilda_LOG_DIR_NOT_EXISTS'] = 'directory will be created automatically on first write';
$MESS['uplab.tilda_LOG_DIR_FOREIGN']    = 'These files in the log directory were not created by the module: #FILES#';
$MESS['uplab.tilda_LOG_DIR_LEGACY']     = 'Logs in the previous format (before version 3.3.2, one file per day): #COUNT# file(s), #SIZE#. The module does not delete them — they may still be needed to investigate an incident. Use the "Clear logs" button to remove them.';

$MESS['uplab.tilda_CHECK_CONNECTION']          = 'Test connection';
$MESS['uplab.tilda_CHECK_CONN_CHECKING']       = 'Checking...';
$MESS['uplab.tilda_CHECK_CONN_EMPTY_KEYS']     = 'Please enter both the public and secret keys.';
$MESS['uplab.tilda_CHECK_CONN_SUCCESS']        = 'Connection successful. Projects found: #COUNT#.';
$MESS['uplab.tilda_CHECK_CONN_API_ERROR']      = 'Tilda API error';
$MESS['uplab.tilda_CHECK_CONN_CURL_ERROR']     = 'cURL error: ';
$MESS['uplab.tilda_CHECK_CONN_INVALID_RESP']   = 'Invalid response from Tilda API.';

$MESS['uplab.tilda_CLEAR_LOGS']         = 'Delete log files:';
$MESS['uplab.tilda_CLEAR_LOGS_BTN']     = 'Clear logs';
$MESS['uplab.tilda_CLEAR_LOGS_CONFIRM'] = 'Delete all log files? This action cannot be undone.';
$MESS['uplab.tilda_LOGS_CLEARED']         = 'Logs cleared (#COUNT# file(s)).';
$MESS['uplab.tilda_LOGS_CLEARED_FOREIGN'] = 'Files not created by the module are left in the directory and were not deleted: #FILES#';


$MESS['uplab.tilda_RESILIENCE_SECTION'] = 'Behaviour when Tilda fails';
$MESS['uplab.tilda_ATTEMPTS'] = 'Number of request attempts (1-5). Tilda sometimes stalls while sending heavy pages, and the next attempt completes:';
$MESS['uplab.tilda_STALL_TIMEOUT'] = 'Treat the transfer as stalled after this many seconds without new bytes (0 - do not check). Only a transfer that has already started is checked, waiting for the first byte is never aborted:';
$MESS['uplab.tilda_STALE_ON_ERROR'] = 'Serve the stale page copy when Tilda fails:';
$MESS['uplab.tilda_STALE_NOTE'] = 'Without this option the Tilda block disappears from the page on a transport failure. With it enabled the last successfully loaded version is served (the copy is kept for 30 days), an error notice appears in the admin panel, a warning goes to the event log, and staff see a stale-version notice on the page itself - visitors do not. A substantive API response (error, page not found, or empty page) is never replaced with the copy because the page may have been unpublished. The copy is removed when the page cache or the whole cache is cleared.';

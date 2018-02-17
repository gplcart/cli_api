[![Build Status](https://scrutinizer-ci.com/g/gplcart/cli_api/badges/build.png?b=master)](https://scrutinizer-ci.com/g/gplcart/cli_api/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/gplcart/cli_api/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/gplcart/cli_api/?branch=master)

A processor for [API](https://github.com/gplcart/api) module. Essentially it translates HTTP requests into CLI commands and allows to execute CRUD commands provided by different CLI providers.

Every request must be constructed as such: `/api/cli-command-name`

Command options can be passed in the query string, POST body or both. Arguments from POST body will override arguments from GET query.

**Examples**

Get list of system events in JSON format. Use `event-get` from [CLI module](https://github.com/gplcart/cli):

    GET /api/event-get HTTP/1.1

With options, list only those with "danger" severity:

    GET /api/event-get?severity=danger HTTP/1.1

**Requirements:**

- [API](https://github.com/gplcart/api)
- Enabled [exec()](http://php.net/manual/en/function.exec.php) function

**Installation**

1. Download and extract to `system/modules` manually or using composer `composer require gplcart/cli_api`. IMPORTANT: If you downloaded the module manually, be sure that the name of extracted module folder doesn't contain a branch/version suffix, e.g `-master`. Rename if needed.
2. Go to `admin/module/list` end enable the module
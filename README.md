# cradle-admin

General admin user interface with dashboard, configuration. Also comes with some
advance pages like calendars, pipelines and reports

## Install

If you already installed Cradle, you may not need to install this because it
should be already included.

```
$ composer require cradlephp/cradle-auth
$ bin/cradle cradlephp/cradle-auth install
$ bin/cradle cradlephp/cradle-auth sql-populate
```

### Admin Dashboard

Shows quick links to help navigate through the admin as well as database table
stats and history.

```
/admin/dashboard
```

### Admin Configuration

Allows editing of configuration variables for the system.

```
/admin/dashboard
```

### Admin Calendar Page

Define a start Date Field *(required)* and an end Date Field *(optional)*.
Then you can visit this page to see a calendar

```
/admin/system/model/class/calendar?show=[start_date],[end_date]
```

### Pipeline (Experimental)

Pipelines are like project boards. Define a Select Field *(to be used for the columns)*, and a Number Field *(to be used for the ordering)*.

```
/admin/system/model/class/pipeline?show=[select_field]&order=[number_field]
```

Optionally you can add a Price Field to total the columns.

```
/admin/system/model/class/pipeline?show=[select_field]&order=[number_field]&currency=USD&range=[price_field_1],[price_field_2]&total=[price_field_1]
```

Lastly if you make a 1:1 relation with profile, you can add a relation in the parameters as well.

```
/admin/system/model/class/pipeline?show=[select_field]&order=[number_field]&currency=USD&range=[price_field_1],[price_field_2]&total=[price_field_1]&relations=profile
```

----

<a name="contributing"></a>
# Contributing to Cradle PHP

Thank you for considering to contribute to Cradle PHP.

Please DO NOT create issues in this repository. The official issue tracker is located @ https://github.com/CradlePHP/cradle/issues . Any issues created here will *most likely* be ignored.

Please be aware that master branch contains all edge releases of the current version. Please check the version you are working with and find the corresponding branch. For example `v1.1.1` can be in the `1.1` branch.

Bug fixes will be reviewed as soon as possible. Minor features will also be considered, but give me time to review it and get back to you. Major features will **only** be considered on the `master` branch.

1. Fork the Repository.
2. Fire up your local terminal and switch to the version you would like to
contribute to.
3. Make your changes.
4. Always make sure to sign-off (-s) on all commits made (git commit -s -m "Commit message")

## Making pull requests

1. Please ensure to run [phpunit](https://phpunit.de/) and
[phpcs](https://github.com/squizlabs/PHP_CodeSniffer) before making a pull request.
2. Push your code to your remote forked version.
3. Go back to your forked version on GitHub and submit a pull request.
4. All pull requests will be passed to [Travis CI](https://travis-ci.org/CradlePHP/cradle-admin) to be tested. Also note that [Coveralls](https://coveralls.io/github/CradlePHP/cradle-admin) is also used to analyze the coverage of your contribution.

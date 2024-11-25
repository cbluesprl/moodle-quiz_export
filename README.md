moodle-quiz_export
==================

This is a quiz report plugin for [moodle](https://moodle.org/) to export quiz attempts as pdf. Single quiz attempt exports are possible as well as bulk exports as zip files.
It has been designed to work with mpdf package.

## Dependencies:
* mpdf (https://mpdf.github.io/). The package is included in the plugin

## Installation:
* switch to /path/to/moodle/mod/quiz/report/
* execute `git clone <this_github_link> export`
* on moodle page go to "Site Administration" -> "Notifications" and follow the instructions

## Technical Information:
* this plugin saves html, pdf and zip files temporary in the folder returned by [sys_get_temp_dir()](http://www.php.net/manual/en/function.sys-get-temp-dir.php) PHP function.
	* this files are created with [tempnam()](http://www.php.net/manual/en/function.tempnam.php) function
	* and modified with [rename()](http://mx2.php.net/manual/en/function.rename.php) and [chmod()](http://mx2.php.net/manual/en/function.chmod.php)
* zip archives are generated with the [ZipArchive](http://mx2.php.net/manual/en/class.ziparchive.php) class

## Practical information:
* Open a quiz page. Then click on the "Results" tab and choose "Quiz exporting" (or "Export de quiz" in french) in the select menu
* On this page, you can choose the export options, then select one (or more) attempts to export in a zip file containing one or more pdfs depending on selection

## Copyright
This plugin is strongly based on [this plugin](https://github.com/elccHTWBerlin/moodle-quiz-export). It has been modified to use mpdf instead of wkhtmltopdf.

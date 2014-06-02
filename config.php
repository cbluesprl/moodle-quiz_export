<?php

/**
 * This file holds the configuration for the quiz_export plugin.
 *
 * @package   quiz_export
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Configuration class
 *
 * @copyright 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_export_config {
	/**
	 * The wkhtmltopdf command with optional options.
	 * --javascript-delay 1000 is recommended because some questiontypes uses javascript for rendering.
	 * And otherwise some js files may be not loaded or ready with calculations until wkhtmltopdf starts rendering.
	 */
	const WKHTMLTOPDF = "wkhtmltopdf";
}

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Keep the per-attempt export links in sync with the export settings form.
 *
 * @module    quiz_export/export_settings
 * @copyright 2026 CBlue Srl
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const FIELDS = ['pagemode', 'hidegeneralfeedback', 'hiderightanswer', 'hideresponsehistory'];

const LINK_SELECTOR = 'a.quiz-export-attempt-link';

/**
 * Read the current value of every export setting present in the form.
 *
 * @returns {Object} Setting name to value, as strings.
 */
const readSettings = () => {
    const settings = {};

    FIELDS.forEach((name) => {
        const field = document.getElementById(`id_${name}`);

        if (!field) {
            return;
        }

        settings[name] = field.type === 'checkbox' ? (field.checked ? '1' : '0') : field.value;
    });

    return settings;
};

/**
 * Rewrite an export link, and the report URL it returns to, with the given settings.
 *
 * @param {HTMLAnchorElement} link The export link to update.
 * @param {Object} settings Setting name to value.
 */
const applySettings = (link, settings) => {
    const url = new URL(link.href, window.location.origin);

    Object.entries(settings).forEach(([name, value]) => url.searchParams.set(name, value));

    const returnurl = url.searchParams.get('returnurl');

    if (returnurl) {
        const target = new URL(returnurl, window.location.origin);

        Object.entries(settings).forEach(([name, value]) => target.searchParams.set(name, value));
        url.searchParams.set('returnurl', `${target.pathname}${target.search}`);
    }

    link.href = url.toString();
};

/**
 * Wire the export settings form to the per-attempt export links.
 */
export const init = () => {
    const links = document.querySelectorAll(LINK_SELECTOR);

    if (!links.length) {
        return;
    }

    const update = () => {
        const settings = readSettings();

        links.forEach((link) => applySettings(link, settings));
    };

    FIELDS.forEach((name) => {
        const field = document.getElementById(`id_${name}`);

        if (field) {
            field.addEventListener('change', update);
        }
    });

    update();
};

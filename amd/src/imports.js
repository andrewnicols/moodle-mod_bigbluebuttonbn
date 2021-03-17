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
import config from 'core/config';

export const init = data => {
    document.querySelector('#menuimport_recording_links_select').addEventListener('change', e => {
        const searchUrl = new URL(`${config.wwwroot}/mod/bigbluebuttonbn/import_view.php`);
        searchUrl.set('bn', data.bn);
        searchUrl.set('tc', e.target.value);

        window.location = searchUrl.toString();
    });
};

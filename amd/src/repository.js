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

import {call as fetchMany} from 'core/ajax';

const getListTableRequest = bigbluebuttonbnid => {
    return {
        methodname: 'mod_bigbluebutton_recording_list_table',
        args: {
            bigbluebuttonbnid,
        }
    };
};

/**
 * Fetch the list of recordings from the server.
 *
 * @param   {Number} bigbluebuttonbnid The instance ID
 * @returns {Promise}
 */
export const fetchRecordings = bigbluebuttonbnid => fetchMany([getListTableRequest(bigbluebuttonbnid)])[0];

/**
 * Perform an update on a single recording.
 *
 * @param   {object} args The instance ID
 * @returns {Promise}
 */
export const updateRecording = args => fetchMany([
    {
        methodname: 'mod_bigbluebutton_recording_update_recording',
        args,
    },
    getListTableRequest(args.bigbluebuttonbnid),
])[1];

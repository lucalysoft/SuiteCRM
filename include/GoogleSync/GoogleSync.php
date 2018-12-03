<?php

/**
 *
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.
 *
 * SuiteCRM is an extension to SugarCRM Community Edition developed by SalesAgility Ltd.
 * Copyright (C) 2011 - 2018 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *
 * You can contact SugarCRM, Inc. headquarters at 10050 North Wolfe Road,
 * SW2-130, Cupertino, CA 95014, USA. or at email address contact@sugarcrm.com.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * SugarCRM" logo and "Supercharged by SuiteCRM" logo. If the display of the logos is not
 * reasonably feasible for technical reasons, the Appropriate Legal Notices must
 * display the words "Powered by SugarCRM" and "Supercharged by SuiteCRM".
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

/**
 * Implements Google Calendar Syncing
 *
 * @license https://raw.githubusercontent.com/salesagility/SuiteCRM/master/LICENSE.txt
 * GNU Affero General Public License version 3
 * @author Benjamin Long <ben@offsite.guru>
 */
require_once __DIR__ . '/../../modules/Users/User.php';
require_once __DIR__ . '/../../modules/Meetings/Meeting.php';

class GoogleSync
{

    /** @var array An array of user id's we are going to sync for */
    public $users = array();

    /** @var User The SuiteCRM User Bean we're currently working with */
    public $workingUser;

    /** @var \Google_Client The Google client object for the current sync job */
    public $gClient;

    /** @var \Google_Service_Calendar The Google Calendar Service Object */
    public $gService;

    /** @var array The Google AuthcConfig json */
    public $authJson = array();

    /** @var string The local timezone */
    public $timezone;

    /** @var string The Calendar ID */
    public $calendarId;

    /** @var array An array of SuiteCRM meeting id's that we've already synced this session */
    public $syncedList = array();

    /** @var object A Database Instance */
    private $db;

    /** @var object A Logger Instance */
    private $logger;

    /** @var string The log level before we begin */
    private $oldLogLevel;

    /**
     * Class Constructor
     */
    public function __construct()
    {
        // This sets the log level to a variable that can be set on the command line while running cron.php on the server. It's for debugging only.
        // EXAMPLE: $ GSYNC_LOGLEVEL=debug php cron.php
        $this->logger = LoggerManager::getLogger();
        if (isset($_SERVER['GSYNC_LOGLEVEL'])) {
            $this->oldLogLevel = $this->logger->getLogLevel();
            $this->logger->setLevel($_SERVER['GSYNC_LOGLEVEL']);
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Log Level Set To: ' . $_SERVER['GSYNC_LOGLEVEL']);
        }
        $this->timezone = date_default_timezone_get(); // This defaults to the server timezone. Overridden later.
        $this->authJson = $this->getAuthJson();
        $this->db = DBManagerFactory::getInstance();
        $this->logger->debug(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . '__construct');
    }

    /**
     * Class Destructor
     */
    public function __destruct()
    {
        // Set the log level back to the original value
        $this->logger->debug(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . '__destruct');
        if (isset($this->oldLogLevel)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Setting Log Level Back To: ' . $this->oldLogLevel);
            $this->logger->setLevel($this->oldLogLevel);
        }
    }

    /**
     * Gets the auth json string from the system
     *
     * @return array|false json on success, false on failure
     */
    public function getAuthJson()
    {
        global $sugar_config;
        $authJson_local = json_decode(base64_decode($sugar_config['google_auth_json']), true);
        if (!$authJson_local) {
            // The authconfig json string is invalid json
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'AuthConfig is not proper JSON string');
            return false;
        } elseif (!array_key_exists('web', $authJson_local)) {
            // The authconfig is valid json, but the 'web' key is missing. This is not a valid authconfig.
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'AuthConfig is missing required [web] key');
            return false;
        } else {
            return $authJson_local;
        }
    }

    /**
     * Add a user to the list of users to sync
     *
     * The user id is used as the key
     *
     * @param string $id : the SuiteCRM user id
     * @param string $name : the SuiteCRM user name.
     *  Not really used for anything other than reference.
     *
     * @return bool Success/Failure
     */
    public function addUser($id, $name)
    {
        if (array_key_exists($id, $this->users)) {
            $this->logger->warn(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . $id . ' already set');
            return false;
        } else {
            $this->users[$id] = $name;
            $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . $id . ' set to ' . $this->users[$id]);
            return true;
        }
    }

    /**
     * Creates and Sets the Google client in the object
     *
     * @param string $id : the SuiteCRM user id
     *
     * @return bool Success/Failure
     */
    public function setClient($id)
    {
        if (!$gClient_local = $this->getClient($id)) {
            return false;
        } else {
            $this->gClient = $gClient_local;
            return true;
        }
    }

    /**
     * Set the Google client up for the user by id
     *
     * @param string $id : the SuiteCRM user id
     *
     * @return \Google_Client|false Google_Client on success. False on failure.
     */
    public function getClient($id)
    {
        // Retrieve user bean
        if (!isset($this->workingUser)) {
            $this->workingUser = BeanFactory::getBean('Users', $id);
            if (!$this->workingUser) {
                throw new Exception('Unable to retrieve a User bean');
            }
        }

        // Retrieve Access Token JSON from user preference
        $accessToken = json_decode(base64_decode($this->workingUser->getPreference('GoogleApiToken', 'GoogleSync')), true);
        if (!array_key_exists('access_token', $accessToken)) {
            // The Token is invalid JSON or missing
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Invalid or Missing AuthToken');
            return false;
        }
        // The refresh token is only provided once, on first authentication. It must be added afterwards.
        if (!array_key_exists('refresh_token', $accessToken)) {
            $accessToken['refresh_token'] = base64_decode($this->workingUser->getPreference('GoogleApiRefreshToken', 'GoogleSync'));
        }

        // New Google Client anf refresh the token if needed
        $client = $this->getGoogleClient($accessToken);

        if (!$client) {
            return false;
        } else {
            return $client;
        }
    }

    /**
     * New Google Client anf refresh the token if needed
     *
     * @param array $accessToken
     * @return \Google_Client or false on Exception
     */
    protected function getGoogleClient($accessToken)
    {
        // New Google Client
        $client = new Google_Client();
        $client->setApplicationName('SuiteCRM');
        $client->setScopes(Google_Service_Calendar::CALENDAR);
        $client->setAccessType('offline');
        $client->setAuthConfig($this->authJson);
        $client->setAccessToken($accessToken);

        // Refresh the token if needed
        if ($client->isAccessTokenExpired()) {
            $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Refreshing Access Token');
            $refreshToken = $client->getRefreshToken();
            if (isempty($refreshToken)) {
                throw new Exception('Refresh token is missing');
                return false;
            } else {
                try {
                    $client->fetchAccessTokenWithRefreshToken($refreshToken);
                } catch (Exception $e) {
                    $this->logger->fatal('Caught exception: ',  $e->getMessage());
                    return false;
                }
            }
            // Save new token to user preference
            $this->workingUser->setPreference('GoogleApiToken', base64_encode(json_encode($client->getAccessToken())), 'GoogleSync');
            $this->workingUser->savePreferencesToDB();
        }

        return $client;
    }

    /**
     * Retrieve List of meetings owned by the Current Working User
     *
     *
     * @return array Array of SuiteCRM Meeting Beans
     */
    public function getUserMeetings()
    {

        // We do it this way so we also get deleted meetings
        $query = "SELECT id FROM meetings WHERE assigned_user_id = '" . $this->workingUser->id . "' AND date_start <= now() + interval 3 month";
        $result = $this->db->query($query);

        $meetings = array();
        while ($row = $this->db->fetchByAssoc($result)) {
            $meeting = BeanFactory::getBean('Meetings');
            if (!$meeting) {
                throw new Exception('Unable to get Meetings bean.');
            }
            $meeting->retrieve($row['id'], true, false);
            $meetings[] = $meeting;
        }
        return $meetings;
    }

    /**
     * Set user's google calendar id
     *
     *
     * @return bool|int Success/Failure calendar ID if success
     */
    public function setUsersGoogleCalendar()
    {

        // Make sure we have a Google Calendar Service instance
        if (!$this->isServiceExists()) {
            return false;
        }

        // get list of users calendars
        $calendarList = $this->gService->calendarList->listCalendarList();

        // find the id of the 'SuiteCRM' calendar ... this may stay or go
        $this->findSuiteCRMCalendar($calendarList);

        // if the SuiteCRM calendar doesn't exist... Create it!
        if (!$this->isCalendarExists()) {
            $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Unable to find the SuiteCRM Google Calendar, Creating it!');
            $calendar = new Google_Service_Calendar_Calendar();
            $calendar->setSummary('SuiteCRM');
            $calendar->setTimeZone($this->timezone);

            $createdCalendar = $this->gService->calendars->insert($calendar);
            $this->calendarId = $createdCalendar->getId();
        }

        // Final check to make sure we have an ID
        if (!$this->isCalendarExists()) {
            return false;
        } else {
            return $this->calendarId;
        }
    }

    /**
     * find the id of the 'SuiteCRM' calendar ... this may stay or go
     *
     * @param Google_Service_Calendar_CalendarList $calendarList
     */
    protected function findSuiteCRMCalendar(Google_Service_Calendar_CalendarList $calendarList)
    {
        foreach ($calendarList->getItems() as $calendarListEntry) {
            if ($calendarListEntry->getSummary() == 'SuiteCRM') {
                $this->calendarId = $calendarListEntry->getId();
                break;
            }
        }
    }

    /**
     * Get events in users google calendar
     *
     *
     * @return bool|array Array of Google_Service_Calendar_Event Objects
     */
    public function getUserGoogleEvents()
    {

        // Make sure we have a Google Calendar Service instance and
        // Make sure we have a calendar id
        if (!$this->isServiceExists() || !$this->isCalendarExists()) {
            return false;
        }

        // Set Options for what events we get from Google
        $optParams = array(
            'maxResults' => 500,
            'showDeleted' => true,
            'singleEvents' => true,
            'timeMin' => date('c', strtotime('-1 month')),
            'timeMax' => date('c', strtotime('+3 month')),
        );

        $results_g = $this->gService->events->listEvents($this->calendarId, $optParams);

        // We only want the events, not the leading cruft
        $results = $results_g->getItems();

        if (empty($results)) {
            $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'No events found.');
            return array();
        } else {
            $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Found ' . count($results) . ' Google Events');
            return $results;
        }
    }

    /**
     * Make sure we have a Google Calendar Service instance
     *
     * @return boolean
     */
    protected function isServiceExists()
    {
        // Make sure we have a Google Calendar Service instance
        if (!isset($this->gService)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'The Google Service is not set up. See setGService Method.');
            return false;
        }
        return true;
    }

    /**
     * Make sure we have a calendar id
     *
     * @return boolean
     */
    protected function isCalendarExists()
    {
        // Make sure we have a calendar id
        if (!isset($this->calendarId)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'The calendar ID is not set. See setUsersGoogleCalendar Method');
            return false;
        }
        return true;
    }

    /**
     * Get a google event by the matching SuiteCRM meeting id
     *
     * @param string $meeting_id SuiteCRM Meeting ID
     *
     * @return \Google_Service_Calendar_Event A Google_Service_Calendar_Event Object
     */
    public function getGoogleEventByMeetingId($meeting_id)
    {

        // Make sure the calendar service is set up
        if (!$this->isServiceExists()) {
            return false;
        }

        // Set Options for what events we get from Google
        $optParams = array(
            'maxResults' => 2, // We ask for a max of 2 because we should *NEVER* get more than 1. If we get 2, something is horribly wrong.
            'showDeleted' => true,
            'singleEvents' => false,
            'timeMin' => date('c', strtotime('-6 month')),
            'privateExtendedProperty' => 'suitecrm_id=' . $meeting_id
        );

        $results_g = $this->gService->events->listEvents($this->calendarId, $optParams);
        $results = $results_g->getItems();

        $first = $this->getFirstGoogleEventByMeetingId($results);
        return $first;
    }

    /**
     *
     * @param array $results
     */
    protected function getFirstGoogleEventByMeetingId($results)
    {
        if (count($results) > 1) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'We received more than one Google event with the same SuiteCRM meeting ID. Something is horribly wrong!');
            return false;
        } elseif (count($results) == 0) { // No events match. Return emtpy array.
            return array();
        } else {
            $gEvent = $results[0];
            return $gEvent;
        }
    }

    /**
     * Get a google event by the event id
     *
     * @param string $event_id Google Event ID
     *
     * @return \Google_Service_Calendar_Event|null Google_Service_Calendar_Event if found, null if not found
     */
    public function getGoogleEventById($event_id)
    {

        if (empty($event_id)) {
            // If we didn't get passed an event id, throw an exception
            throw new \InvalidArgumentException('event ID is empty');
        }

        // Make sure the calendar service is set up
        if (!$this->isServiceExists()) {
            throw new \RuntimeException('Cannot Continue Without Google Service');
        }

        $gEvent = $this->gService->events->get($this->calendarId, $event_id);

        if (!empty($gEvent->getId())) {
            return $gEvent;
        } else {
            return null;
        }
    }

    /**
     * Get a SuiteCRM meeting by Google Event ID
     *
     * @param string $event_id The Google Event ID
     *
     * @return \Meeting|bool SuiteCRM Meeting Bean if found, false on error, null if not found
     */
    public function getMeetingByEventId($event_id)
    {

        // We do it this way so we also get deleted meetings
        $query = "SELECT id FROM meetings WHERE gsync_id = '{$event_id}'";
        $result = $this->db->query($query);

        // This checks to make sure we only get one result. If we get more than one, something is inconsistant in the DB
        if ($result->num_rows > 1) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'More than one meeting matches Google Id: ' . $event_id);
            return false;
        } elseif ($result->num_rows == 0) {
            return; // No matches Found
        }

        $meeting = BeanFactory::getBean('Meetings');
        if (!$meeting) {
            throw new Exception('Unable to get Meetings bean.');
        }
        $row = $this->db->fetchByAssoc($result);
        $meeting->retrieve($row['id'], true, false);

        return $meeting;
    }

    /**
     * Creates and Sets $this->gService to a valid Google Calendar Service
     *
     * @return bool Success/Failure
     */
    public function setGService()
    {
        // make sure we have a client set
        if (!isset($this->gClient)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'The Google Client is not set up. See setClient Method');
            return false;
        }

        // create new calendar service
        $this->gService = new Google_Service_Calendar($this->gClient);
        if ($this->isServiceExists()) {
            return true;
        } else {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Setting $this->gService Failed');
            return false;
        }
    }

    /**
     * Helper function for pushPullSkip.
     *
     * When given a single calendar object, determine its type and return an action.
     *
     * @param Meeting|\Google_Service_Calendar_Event $event Either the Meeting Bean or Google_Service_Calendar_Event Object
     *
     * @return string push, pull, skip, or false on error
     */
    public function singleEventAction($event)
    {
        switch (get_class($event)) {
            case "Google_Service_Calendar_Event":
                if ($event->status !== 'cancelled') {
                    // We only pull if the Google Event is not deleted/cancelled
                    $return = "pull";
                } else {
                    $return = "skip";
                }
                break;
            case "Meeting":
                if ($event->deleted == '0') {
                    // We only push if the meeting is not deleted
                    $return = "push";
                } else {
                    $return = "skip";
                }
                break;
            default:
                $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'I don\'t understand the event type you used.');
                $return = false;
        }

        return $return;
    }

    /**
     * Helper function for pushPullSkip
     *
     * Takes two objects, and determines which one is local and which is remote.
     *
     * @param Meeting|\Google_Service_Calendar_Event $event_1 Either the Meeting Bean or Google_Service_Calendar_Event Object
     * @param Meeting|\Google_Service_Calendar_Event $event_2 Either the Meeting Bean or Google_Service_Calendar_Event Object
     *
     * @return array ['local']=Meeting Bean, ['remote']=Google_Service_Calendar_Event object
     */
    public function sortEventObjects($event_1, $event_2)
    {
        if ($this->isValidEventPair($event_1, $event_2)) {
            $events_array = array();

            // We have two events... figure out which is which and set the internal objects
            $event1Array = $this->getEventArray($event_1);
            if (!$event1Array) {
                return false;
            }
            $events_array = array_merge($events_array, $event1Array);

            $event2Array = $this->getEventArray($event_2);
            if (!$event2Array) {
                return false;
            }
            $events_array = array_merge($events_array, $event2Array);

            return $events_array;
        }
        return false;
    }

    /**
     * 
     * @param array $event
     * @return boolean
     */
    protected function getEventArray($event)
    {
        $events_array = array();
        $obj_class = get_class($event);
        if ($obj_class == 'Meeting') {
            $events_array['local'] = $event;
        } elseif ($obj_class == 'Google_Service_Calendar_Event') {
            $events_array['remote'] = $event;
        } else {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Events must be of type \'Meeting\' or \'Google_Service_Calendar_Event\'');
            return false;
        }
        return $events_array;
    }

    /**
     * 
     * @param array $event_1
     * @param array $event_2
     * @return boolean
     */
    protected function isValidEventPair($event_1, $event_2)
    {
        if (!isset($event_1) || empty($event_1) || !isset($event_2) || empty($event_2)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'You must pass two events event');
            return false;
        }
        return true;
    }

    /**
     * Figure out if we need to push/pull an update, or do nothing.
     *
     * Used when an event w/ a matching ID is on both ends of the sync.
     *
     * @param Meeting|\Google_Service_Calendar_Event $event_1 Either the Meeting Bean or Google_Service_Calendar_Event Object
     * @param \Google_Service_Calendar_Event $event_2 (optional) Google Event
     *
     * @return string|bool push(_delete), pull(_delete), skip
     */
    public function pushPullSkip($event_1, $event_2 = null)
    {
        if ((!isset($event_1) || empty($event_1)) && (!isset($event_2) || empty($event_2))) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'You must pass at least one event');
            return false;
        }

        // Did we only get one event?
        if (!isset($event_2) || empty($event_2)) {
            // If we only got one event, figure out which kind it is, and pass the return from the helper function
            return $this->singleEventAction($event_1);
        } else {
            // For two events, pass to the helper function that sorts them and returns an array of objects
            $events_array = $this->sortEventObjects($event_1, $event_2);

            // We don't need the original vars now
            unset($event_1);
            unset($event_2);

            // Check if we already sync'ed this event on this run
            if (in_array($events_array['local']->id, $this->syncedList, true)) {
                $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'We already synced this meeting. Marking to skip.');
                return "skip";
            }

            // Before we get further, if both events are deleted, skip
            if ($events_array['local']->deleted == '1' && $events_array['remote']->status == 'cancelled') {
                return "skip";
            }

            // Get the last modified time from google event
            $gModified = strtotime($events_array['remote']->getUpdated());

            // Get last modified of SuiteCRM event
            $sModified = strtotime($events_array['local']->fetched_row['date_modified'] . ' UTC'); // SuiteCRM stores the timedate as UTC in the DB
            // Get the last sync time of SuiteCRM event
            if (isset($events_array['local']->fetched_row['gsync_lastsync'])) {
                $lastSync = $events_array['local']->fetched_row['gsync_lastsync'];
            } else {
                $lastSync = 0;
            }

            // Event has not been modified since last sync... skip
            if ($gModified <= $lastSync && $sModified <= $lastSync) {
                $this->syncedList[] = $events_array['local']->id;
                return "skip";
            }

            // Event was modified since last sync
            if ($gModified > $lastSync || $sModified > $lastSync) {
                if ($gModified > $sModified) {
                    if ($events_array['remote']->status == 'cancelled') {
                        // if the remote event is deleted, delete it here
                        return "pull_delete";
                    } else {
                        return "pull";
                    }
                } else {
                    if ($events_array['local']->deleted == '1') {
                        return "push_delete";
                    } else {
                        return "push";
                    }
                }
            }
        }

        return "unknown"; // we should never get here
    }

    /**
     * Push event from SuiteCRM to Google Calendar
     *
     * If the google event is not provided, a new one will be created
     * and inserted. If one is provided, the existing Google Event will
     * be updated.
     *
     * @param Meeting $event_local : SuiteCRM Meeting Bean
     * @param \Google_Service_Calendar_Event $event_remote (optional) \Google_Service_Calendar_Event Object
     *
     * @return bool Success/Failure
     */
    public function pushEvent(Meeting $event_local, Google_Service_Calendar_Event $event_remote = null)
    {
        if (!isset($event_remote) || empty($event_remote)) {
            $event = $this->createGoogleCalendarEvent($event_local);
            $return = $this->gService->events->insert($this->calendarId, $event);
        } else {
            $event = $this->updateGoogleCalendarEvent($event_local, $event_remote);
            $return = $this->gService->events->update($this->calendarId, $event->getId(), $event);
        }

        // Set the SuiteCRM Meeting's last sync timestamp, and google id
        $this->setLastSync($event_local, $return->getId());

        /* We don't get a status code back showing success. Instead, the return of the
         * create or update is the Google_Service_Calendar_Event object after saving.
         * So we check to make sure it has an ID to determine Success/Failure.
         */
        if (isset($return->id)) {
            return $return->id;
        } else {
            return false;
        }
    }

    /**
     * Helper Function to get a Google_Service_Calendar_EventExtendedProperties object for the Google event
     *
     * Takes the local and remote events, and returns a Google_Service_Calendar_EventExtendedProperties
     *
     * @param \Google_Service_Calendar_Event $event_remote \Google_Service_Calendar_Event Object
     * @param Meeting $event_local Meeting (optional) \Meeting Bean
     *
     * @return Google_Service_Calendar_EventExtendedProperties object
     */
    public function returnExtendedProperties(Google_Service_Calendar_Event $event_remote, Meeting $event_local)
    {
        // We pull the existing extendedProperties, and change our values
        // That way we don't mess with anything else that's using other values.
        $extendedProperties = $event_remote->getExtendedProperties();

        if (!empty($extendedProperties)) {
            $private = $extendedProperties->getPrivate();
        } else {
            $extendedProperties = new Google_Service_Calendar_EventExtendedProperties;
            $private = array();
        }

        $private['suitecrm_id'] = $event_local->id;
        $private['suitecrm_type'] = $event_local->module_name;

        $extendedProperties->setPrivate($private);

        return $extendedProperties;
    }

    /**
     * Pull event from Google Calendar to SuiteCRM
     *
     * If the SuiteCRM Meeting is not provided, a new one will be created
     * and inserted. If one is provided, the existing meeting will be updated.
     *
     * @param \Google_Service_Calendar_Event $event_remote \Google_Service_Calendar_Event Object
     * @param Meeting $event_local Meeting (optional) \Meeting Bean
     *
     * @return bool Success/Failure of setLastSync, since that's what saves the record
     */
    public function pullEvent(Google_Service_Calendar_Event $event_remote, Meeting $event_local = null)
    {
        if (!isset($event_local) || empty($event_local)) {
            $event = $this->createSuitecrmMeetingEvent($event_remote);
        } else {
            $event = $this->updateSuitecrmMeetingEvent($event_local, $event_remote);
        }

        // We need to set the suitecrm_ private properties in the Google event here,
        // Otherwise it's seen as a new event next time we sync
        $extendedProperties = $this->returnExtendedProperties($event_remote, $event);
        $event_remote->setExtendedProperties($extendedProperties);

        $greturn = $this->gService->events->update($this->calendarId, $event_remote->getId(), $event_remote);

        /* We don't get a status code back showing success. Instead, the return of the
         * create or update is the Google_Service_Calendar_Event object after saving.
         * So we check to make sure it has an ID to determine Success/Failure.
         */
        if (isset($greturn->id)) {
            return $this->setLastSync($event, $event_remote->getId());
        } else {
            return false;
        }
    }

    /**
     * Delete SuiteCRM Meeting
     *
     * @param Meeting $meeting SuiteCRM Meeting Bean
     *
     * @return bool Success/Failure of setLastSync, since that's what saves the record
     */
    public function delMeeting(Meeting $meeting)
    {
        $meeting->deleted = '1';
        $meeting->gsync_id = '';
        return $this->setLastSync($meeting);
    }

    /**
     * Delete Google Event
     *
     * @param \Google_Service_Calendar_Event $event \Google_Service_Calendar_Event Object
     * @param String $meeting_id SuiteCRM Meeting Id
     *
     * @return bool Success/Failure
     */
    public function delEvent(Google_Service_Calendar_Event $event, $meeting_id)
    {
        // Make sure the calendar service is set up
        if (!$this->isServiceExists()) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'The Google Service is not set up. See setGService Method.');
            return false;
        }

        // Make sure we got a meeting_id
        if (!isset($meeting_id)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'This function requires a meeting id as the 2nd parameter');
            return false;
        }

        $return = $this->gService->events->delete($this->calendarId, $event->getId());

        // Pull the status code returned to determine Success/Failure
        $statusCode = $return->getStatusCode();

        if ($statusCode >= 200 && $statusCode <= 299) {
            // 2xx statusCode = success
            $this->logger->debug(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Received Success Status Code: ' . $statusCode . ' on delete.');

            // This removes the gsync_id reference from the table.
            $sql = "UPDATE meetings SET gsync_id = '' WHERE id = '" . $meeting_id . "'";
            $res = $this->db->query($sql);
            if (!$res) {
                $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Failed to remove gsync_id from record' . $meeting_id);
            }

            $this->syncedList[] = $meeting_id;
            return true;
        } else {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Received Failure Status Code: ' . $statusCode . ' on delete!');
            return false;
        }
    }

    /**
     * Helper function. Clear all popup reminders from crm meeting
     *
     * @param string $event_id The ID of the event in the DB
     *
     * @return bool Success/Failure
     */
    public function clearPopups($event_id)
    {
        if (!isset($event_id) || empty($event_id)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Event_id is missing');
            return false;
        }

        // Disable all popup reminders for the SuiteCRM meeting
        $eventIdQuoted = $this->db->quoted($event_id);
        $sql = sprintf("UPDATE reminders SET popup = '0' WHERE related_event_module_id = %s AND deleted = '0'", $eventIdQuoted);
        $res = $this->db->query($sql);
        if (!$res) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'SQL Failure!');
            return false;
        }

        // Mark all reminders where both popup and email are disabled as deleted.
        $sql = sprintf("UPDATE reminders SET deleted = '1' WHERE popup = '0' AND email = '0' AND related_event_module_id = %s AND deleted = '0'", $eventIdQuoted);
        $res = $this->db->query($sql);
        if (!$res) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'SQL Failure!');
            return false;
        }

        return true;
    }

    /**
     * Update SuiteCRM Meeting from Google Calendar Event
     *
     * @param Meeting $event_local SuiteCRM Meeting Bean
     * @param \Google_Service_Calendar_Event $event_remote Google_Service_Calendar_Event Object
     *
     * @return Meeting SuiteCRM Meeting Bean
     */
    public function updateSuitecrmMeetingEvent(Meeting $event_local, Google_Service_Calendar_Event $event_remote)
    {
        if ((!isset($event_local) || empty($event_local)) || (!isset($event_remote) || empty($event_remote))) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'ERROR:Missing Variables');
            return false;
        }

        $event_local->name = $event_remote->getSummary();
        $event_local->description = $event_remote->getDescription();
        $event_local->location = $event_remote->getLocation();

        // Get Start/End/Duration from Google Event
        $starttime = strtotime($event_remote->getStart()['dateTime']);
        $endtime = strtotime($event_remote->getEnd()['dateTime']);
        $diff = abs($starttime - $endtime);
        $tmins = $diff / 60;
        $hours = floor($tmins / 60);
        $mins = $tmins % 60;

        // Set Start/End/Duration in SuiteCRM Meeting and Assigned User
        $event_local->date_start = date("m/d/Y h:i:sa", $starttime);
        $event_local->date_end = date("m/d/Y h:i:sa", $endtime);
        $event_local->duration_hours = $hours;
        $event_local->duration_minutes = $mins;
        $event_local->assigned_user_id = $this->workingUser->id;

        // Disable all popup reminders for the SuiteCRM meeting. We add them back from Google event below.
        $event_id = $event_local->id;
        $this->clearPopups($event_id);

        // Get Google Event Popup Reminders
        $gReminders = $event_remote->getReminders();
        $overrides = $gReminders->getOverrides();

        // Create a new popup reminder for each google reminder
        foreach ($overrides as $override) {
            if ($override->getMethod() == 'popup') {
                $sReminder = BeanFactory::getBean('Reminders');
                if (!$sReminder) {
                    throw new Exception('Unable to get Reminder bean.');
                }
                $sReminder->popup = '1';
                $sReminder->timer_popup = $override->getMinutes() * 60;
                $sReminder->related_event_module = $event_local->module_name;
                $sReminder->related_event_module_id = $event_local->id;
                $reminderId = $sReminder->save(false);

                $reminderInvitee = BeanFactory::getBean('Reminders_Invitees');
                if (!$reminderInvitee) {
                    throw new Exception('Unable to get Reminders_Invitees bean.');
                }
                $reminderInvitee->reminder_id = $reminderId;
                $reminderInvitee->related_invitee_module = 'Users';
                $reminderInvitee->related_invitee_module_id = $this->workingUser->id;
                $reminderInvitee->save(false);
            }
        }
        return $event_local;
    }

    /**
     * Create SuiteCRM Meeting event
     *
     * @param \Google_Service_Calendar_Event $event_remote The Google_Service_Calendar_Event we're creating a SuiteCRM Meeting for
     *
     * @return Meeting SuiteCRM Meeting Bean
     */
    public function createSuitecrmMeetingEvent(Google_Service_Calendar_Event $event_remote)
    {
        $this->logger->debug(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Creating New SuiteCRM Meeting');
        $meeting = BeanFactory::getBean('Meetings');
        if (!$meeting) {
            throw new Exception('Unable to get Meeting bean.');
        }
        $meeting->id = create_guid();
        $meeting->new_with_id = true;
        $event_local = $this->updateSuitecrmMeetingEvent($meeting, $event_remote);
        return $event_local;
    }

    /**
     * Update Google Calendar Event from SuiteCRM Meeting
     *
     * @param Meeting $event_local SuiteCRM Meeting Bean
     * @param \Google_Service_Calendar_Event $event_remote Google Event Object
     *
     * @return \Google_Service_Calendar_Event
     */
    public function updateGoogleCalendarEvent(Meeting $event_local, Google_Service_Calendar_Event $event_remote)
    {
        if ((!isset($event_local) || empty($event_local)) || (!isset($event_remote) || empty($event_remote))) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'ERROR:Missing Variables');
            return false;
        }

        $event_remote->setSummary($event_local->name);
        $event_remote->setDescription($event_local->description);
        $event_remote->setLocation($event_local->location);

        $startDateTime = new Google_Service_Calendar_EventDateTime;
        $startDateTime->setDateTime(date(DATE_ATOM, strtotime($event_local->date_start)));
        $startDateTime->setTimeZone($this->timezone);
        $event_remote->setStart($startDateTime);

        $endDateTime = new Google_Service_Calendar_EventDateTime;
        $endDateTime->setDateTime(date(DATE_ATOM, strtotime($event_local->date_end)));
        $endDateTime->setTimeZone($this->timezone);
        $event_remote->setEnd($endDateTime);

        $extendedProperties = $this->returnExtendedProperties($event_remote, $event_local);
        $event_remote->setExtendedProperties($extendedProperties);

        // Copy over popup reminders
        $event_local_id = $event_local->id;
        $reminders_local = BeanFactory::getBean('Reminders')->get_full_list(
                "", "reminders.related_event_module = 'Meetings'" .
                " AND reminders.related_event_module_id = '$event_local_id'" .
                " AND popup = '1'"
        );

        if ($reminders_local) {
            $reminders_remote = new Google_Service_Calendar_EventReminders;
            $reminders_remote->setUseDefault(false);
            $reminders_array = array();
            foreach ($reminders_local as $reminder_local) {
                $reminder_remote = new Google_Service_Calendar_EventReminder;
                $reminder_remote->setMethod('popup');
                $reminder_remote->setMinutes($reminder_local->timer_popup / 60);
                $reminders_array[] = $reminder_remote;
            }
            $reminders_remote->setOverrides($reminders_array);
            $event_remote->setReminders($reminders_remote);
        }
        return $event_remote;
    }

    /**
     * Create New Google Event object for SuiteCRM Meeting
     *
     * @param Meeting $event_local SuiteCRM Meeting Bean
     *
     * @return \Google_Service_Calendar_Event Google_Service_Calendar_Event Object
     */
    public function createGoogleCalendarEvent(Meeting $event_local)
    {

        //We're creating a new event
        $event_remote_empty = new Google_Service_Calendar_Event;

        $extendedProperties = new Google_Service_Calendar_EventExtendedProperties;
        $extendedProperties->setPrivate(array());

        $event_remote_empty->setExtendedProperties($extendedProperties);

        //Set the Google Event up to match the SuiteCRM one
        $event_remote = $this->updateGoogleCalendarEvent($event_local, $event_remote_empty);

        return $event_remote;
    }

    /**
     * Set the timezone
     *
     * @param string $timezone : timezone_identifier (ie. America/New_York)
     *
     * @return bool Success/Failure
     */
    public function setTimezone($timezone)
    {
        if (!date_default_timezone_set($timezone)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Failed to set timezone to \'' . $timezone . '\'');
            return false;
        } else {
            $this->timezone = date_default_timezone_get();
            $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Timezone set to \'' . $this->timezone . '\'');
            return true;
        }
    }

    /**
     * Set the last sync time for the record
     *
     * This *must* be called *after* the sync is done
     * This also saves the event, so you don't need to do it twice. Just call this.
     * Also adds the id of the event so it doesn't get synced again this session.
     *
     * @param Meeting $event_local SuiteCRM Meeting bean
     * @param string $gEventId (optional) The ID that Google has for the event.
     *
     * @return bool Success/Failure
     */
    protected function setLastSync(Meeting $event_local, $gEventId = null)
    {
        if (isset($gEventId)) {
            $event_local->gsync_id = $gEventId;
        }

        $event_local->gsync_lastsync = time() + 3; // we add three seconds to this, so that the modified time is always older than the gsync_lastsync time
        $return = $event_local->save(false);

        // Set the meeting as accepted by the user, otherwise it doesn't show up on the calendar. We do it here because it must be saved first.
        $event_local->set_accept_status($this->workingUser, 'accept');

        if (isset($return)) {
            $this->syncedList[] = $event_local->id;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Helper function for doSync
     * 
     * @param Meeting $meeting The CRM Meeting
     * @param \Google_Service_Calendar_Event $event The Google Event
     * @param string $action The action to take with the two events
     * 
     * @return bool Success/Failure
     */
    protected function doAction(Meeting $meeting = null, Google_Service_Calendar_Event $event = null, $action)
    {
        if ( !empty($meeting) && !empty($event) ) {
            $title = $meeting->name . " / " . $event->getSummary();
        } elseif ( !empty($meeting) ) {
            $title = $meeting->name;
        } elseif ( !empty($event) ) {
            $title = $event->getSummary();
        } else {
            $title = "UNNAMED RECORD"; // Google doesn't require an event to be named.
        }

        switch ($action) {
            case "push":
                $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Pushing Record: ' . $title);
                return $this->pushEvent($meeting, $event);
                break;
            case "pull":
                $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Pulling Record: ' . $title);
                return $this->pullEvent($event, $meeting);
                break;
            case "skip":
                $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Skipping Record: ' . $title);
                break;
            case "push_delete":
                $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Push Deleting Record: ' . $title);
                return $this->delEvent($event, $meeting->id);
                break;
            case "pull_delete":
                $this->logger->info(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Pull Deleting Record: ' . $title);
                return $this->delMeeting($meeting);
                break;
            default:
                $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Unknown Action: ' . $action . ' for record: ' . $title);
                throw new \InvalidArgumentException('Invalid Action');
                return false;
        }
    }

    /**
     * Perform the sync for a user
     *
     * @param string $id The SuiteCRM user id
     *
     * @return bool Success/Failure
     */
    public function doSync($id)
    {
        if (!$this->setClient($id)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Unable to setup Google Client');
            return false;
        }

        if ($this->workingUser->id == $id) {
            $tz = $this->workingUser->getPreference('timezone', 'global');
            $this->setTimezone($tz);
        } else {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Failed to set the working user and timezone');
            return false;
        }

        if (!$this->setGService()) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Unable to setup Google Service');
            return false;
        }

        if (!$this->setUsersGoogleCalendar()) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Unable to setup Google Calendar Id');
            return false;
        }

        $meetings = $this->getUserMeetings();
        if (empty($meetings)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Unable to get Users Meetings');
            return false;
        }

        // First, we look for SuiteCRM meetings that are not on Google
        foreach ($meetings as $meeting) {
            if ( !empty($meeting->gsync_id) ) {
                $gevent = $this->getGoogleEventById($meeting->gsync_id);
            } else {
                $gevent = null;
            }
            
            $action = $this->pushPullSkip($meeting, $gevent);
            $actionResult = $this->doAction($meeting, $gevent, $action); //TODO: Check this result for success
        }

        // Now, we look at the Google Calendar
        $googleEvents = $this->getUserGoogleEvents();
        if (!isset($googleEvents)) {
            $this->logger->fatal(__FILE__ . ':' . __LINE__ . ' ' . __METHOD__ . ' - ' . 'Unable to get Google Events');
            return false;
        }

        foreach ($googleEvents as $gevent) {
            if (!$meeting = $this->getMeetingByEventId($gevent->getId())) {
                $meeting = null;
            }

            $action = $this->pushPullSkip($gevent, $meeting);
            $actionResult = $this->doAction($meeting, $gevent, $action); //TODO: Check this result for success
        }
        return true;
    }

    /**
     * Setup array of users to sync
     *
     * Fills the $users array with users that are configured to sync
     *
     * @return int added users
     */
    public function setSyncUsers()
    {
        $query = "SELECT id FROM users WHERE deleted = '0'";
        $result = $this->db->query($query);

        $counter = 0;
        while ($row = $this->db->fetchByAssoc($result)) {
            $user = BeanFactory::getBean('Users');
            if (!$user) {
                throw new Exception('Unable to get User bean.');
            }
            $user->retrieve($row['id']);

            if (!empty($user->getPreference('GoogleApiToken', 'GoogleSync')) &&
                    json_decode(base64_decode($user->getPreference('GoogleApiToken', 'GoogleSync'))) &&
                    $user->getPreference('syncGCal', 'GoogleSync') == '1') {
                if ($this->addUser($user->id, $user->full_name)) {
                    $counter++;
                }
            }
        }

        return $counter;
    }

    /**
     * Sync All Configured Users
     *
     * Running this method will collect all users who
     * have Calendar Sync Configured and Enabled and
     * Sync them one by one.
     *
     * @return bool Success/Failure
     */
    public function syncAllUsers()
    {

        // First we populate the array of syncable users
        if (!$this->setSyncUsers()) {
            $this->logger->warn('There is no user to sync..');
        }

        // We count failures here
        $failures = 0;

        // Then we go though the array and sync the users with doSync()
        if (isset($this->users) && !empty($this->users)) {
            foreach (array_keys($this->users) as $key) {
                $return = $this->doSync($key);
                if (!$return) {
                    $this->logger->error('Something went wrong syncing for user id: ' . $key);
                    $failures++;
                }
            }
        }
        if ($failures > 0) {
            $this->logger->warn($failures . ' failure(s) found at syncAllUsers method.');
            return false;
        } else {
            return true;
        }
    }
}

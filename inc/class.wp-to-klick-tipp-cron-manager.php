<?php
    /**
     * Class WpToKlickTippCronManager
     *
     * @version 2.0.0
     * @author Tobias B. Conrad <support@saleswonder.biz>
     */
    class WpToKlickTippCronManager {

        /**
         * Constructor
         */
        public function __construct() {
            $this->addFilter();
            $this->addAction();
        }

        /**
         * Add Cron Filter
         *
         * @access private
         * @return void
         */
        private function addFilter() {
	    add_filter('cron_schedules', array($this, 'addNewSchedule'));
        }

        /**
         * Add the WP Cron Action
         *
         * @access private
         * @return void
         */
        private function addAction() {
            add_action('wpToKlickTippCronAction', 'wpToKlickTippCron');
        }

        /**
         * Set the schedule hooks
         *
         * @access public
         * @return void
         */
        public function setScheduleHook() {
            if (!wp_next_scheduled('wpToKlickTippCronAction')) {
                wp_schedule_event(time(), '6perhour', 'wpToKlickTippCronAction');
            }
        }

        /**
         * Clear the schedule hooks
         *
         * @access public
         * @return void
         */
        public function clearScheduleHook() {
            wp_clear_scheduled_hook('wpToKlickTippCronAction');
        }

        /**
         * Add new schedule for wordpress cron
         *
         * @access public
         * @return array
         */
        public function addNewSchedule($schedules) {
            $hourresult	= '6';
            $inttime = 3600 / $hourresult;
            $schedules['6perhour'] = array(
              'interval'=> $inttime,
              'display'=> __($hourresult . 'times/hour', 'wp-to-klick-tipp-tag-basiertes-e-mail-marketing')
            );

            return $schedules;
        }
    }
?>

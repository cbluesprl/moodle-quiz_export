<?php

/**
 * This file defines the export engine class.
 *
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Quiz export engine class.
 *
 * @package   quiz_export
 * @copyright 2020 CBlue Srl
 * @copyright based on work by 2014 Johannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_export_engine
{
    /**
     * Actual question page assignment like in quiz settings.
     */
    const PAGEMODE_TRUEPAGE = 0;

    /**
     * One question per page.
     */
    const PAGEMODE_QUESTIONPERPAGE = 1;

    /**
     * all questions on single page.
     */
    const PAGEMODE_SINGLEPAGE = 2;

    /**
     * Exports the given quiz attempt to a pdf file.
     *
     * @param quiz_attempt $attemptobj The quiz attempt to export.
     * @param int $pagemode The page break mode used to render the quiz review.
     *                         One of PAGEMODE_TRUEPAGE, PAGEMODE_QUESTIONPERPAGE or PAGEMODE_SINGLEPAGE
     * @return string          File path and name as string of the pdf file.
     */
    public function a2pdf($attemptobj, $pagemode)
    {
        global $CFG;
        $parameters_additionnal_informations = $this->get_additionnal_informations($attemptobj);

        $tmp_dir = $CFG->dataroot . '/mpdf';
        ob_start();
        $tmp_file = tempnam($tmp_dir, "mdl-qexp_");
        ob_get_clean();
        $tmp_pdf_file = $tmp_file . ".pdf";
        rename($tmp_file, $tmp_pdf_file);
        chmod($tmp_pdf_file, 0644);
        ob_start();
        $tmp_file = tempnam($tmp_dir, "mdl-qexp_");
        ob_get_clean();
        $tmp_err_file = $tmp_file . ".txt";
        rename($tmp_file, $tmp_err_file);
        chmod($tmp_err_file, 0644);

        $pdf = new \Mpdf\Mpdf([
            'tempDir' => $tmp_dir,
        ]);

        // Start output buffering html
        ob_start();
        include __DIR__ . '/style/styles.css';
        $css = ob_get_clean();
        $pdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

        $additionnal_informations = '<h3 class="text-center" style="margin-bottom: -20px;">' .
            get_string('documenttitle', 'quiz_export', $parameters_additionnal_informations) .
            '</h3>';

        switch ($pagemode) {
            default:
            case quiz_export_engine::PAGEMODE_TRUEPAGE:
                $html_files = $this->questions_paged($attemptobj);
                break;
            case quiz_export_engine::PAGEMODE_QUESTIONPERPAGE:
                $html_files = $this->question_per_page($attemptobj);
                break;
            case quiz_export_engine::PAGEMODE_SINGLEPAGE:
                $html_files = $this->all_questions($attemptobj);

                // Start output buffering html
                ob_start();
                include $html_files[0];
                $contentHTML = ob_get_clean();

                $contentHTML = preg_replace("/<input.*>/U", '', $contentHTML);
                $pdf->WriteHTML($this->preloadImageWithCurrentSession($additionnal_informations), \Mpdf\HTMLParserMode::HTML_BODY);
                $pdf->WriteHTML($this->preloadImageWithCurrentSession($contentHTML), \Mpdf\HTMLParserMode::DEFAULT_MODE);
                break;
        }
        if ($pagemode == quiz_export_engine::PAGEMODE_TRUEPAGE || $pagemode == quiz_export_engine::PAGEMODE_QUESTIONPERPAGE) {
            $current_page = 0;
            foreach ($html_files as $html_file) {
                // Start output buffering html
                ob_start();
                include __DIR__ . '/style/styles.css';
                $css = ob_get_clean();
                $pdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);

                // Start output buffering html
                ob_start();
                include $html_file;
                $contentHTML = ob_get_clean();
                $contentHTML = preg_replace("/<input.*>/U", '', $contentHTML);
                if ($current_page == 0) {
                    $pdf->WriteHTML($this->preloadImageWithCurrentSession($additionnal_informations), \Mpdf\HTMLParserMode::HTML_BODY);
                }
                $pdf->WriteHTML($this->preloadImageWithCurrentSession($contentHTML), \Mpdf\HTMLParserMode::DEFAULT_MODE);

                if (!$attemptobj->is_last_page($current_page)) {
                    $pdf->AddPage();
                }
                $current_page++;
            }
        }
        $pdf->Output($tmp_pdf_file, \Mpdf\Output\Destination::FILE);

        // Cleanup
        unlink($tmp_err_file);
        foreach ($html_files as $file) {
            unlink($file);
        }

        return $tmp_pdf_file;
    }

    /**
     * Generate the html of all quiz questions (with one question per page)
     * Used with "PAGEMODE_QUESTIONPERPAGE" page mode
     *
     * @param $attemptobj
     * @return array
     */
    protected function question_per_page($attemptobj)
    {
        $tmp_html_files = array();
        $showall = false;
        $num_pages = $attemptobj->get_num_pages();

        for ($page = 0; $page < $num_pages; $page++) {
            $questionids = $attemptobj->get_slots($page);
            $lastpage = $attemptobj->is_last_page($page);

            foreach ($questionids as $questionid) {
                // We have just one question id but an array is required from render function
                $slots = array();
                $slots[] = $questionid;

                $tmp_dir = sys_get_temp_dir();
                $tmp_file = tempnam($tmp_dir, "mdl-qexp_");
                $tmp_html_file = $tmp_file . ".html";
                rename($tmp_file, $tmp_html_file);
                chmod($tmp_html_file, 0644);

                $output = $this->get_review_html($attemptobj, $slots, $page, $showall, $lastpage);

                file_put_contents($tmp_html_file, $output);

                $tmp_html_files[] = $tmp_html_file;
            }
        }

        return $tmp_html_files;
    }

    /**
     * Generate the html of the actual question page
     * Used with "PAGEMODE_TRUEPAGE" page mode
     *
     * @param $attemptobj
     * @return array
     */
    protected function questions_paged($attemptobj)
    {
        $tmp_html_files = array();
        $showall = false;
        $num_pages = $attemptobj->get_num_pages();

        for ($page = 0; $page < $num_pages; $page++) {
            $slots = $attemptobj->get_slots($page);

            $lastpage = $attemptobj->is_last_page($page);

            $tmp_dir = sys_get_temp_dir();
            $tmp_file = tempnam($tmp_dir, "mdl-qexp_");
            $tmp_html_file = $tmp_file . ".html";
            rename($tmp_file, $tmp_html_file);
            chmod($tmp_html_file, 0644);

            $output = $this->get_review_html($attemptobj, $slots, $page, $showall, $lastpage);
            file_put_contents($tmp_html_file, $output);

            $tmp_html_files[] = $tmp_html_file;
        }

        return $tmp_html_files;
    }

    /**
     * Generate the html of all quiz questions
     * Used with "PAGEMODE_SINGLEPAGE" page mode
     *
     * @param $attemptobj
     * @return string[]
     */
    protected function all_questions($attemptobj)
    {
        $slots = $attemptobj->get_slots();
        $showall = true;
        $lastpage = true;
        $page = 0;

        $tmp_dir = sys_get_temp_dir();
        $tmp_file = tempnam($tmp_dir, "mdl-qexp_");
        $tmp_html_file = $tmp_file . ".html";
        rename($tmp_file, $tmp_html_file);
        chmod($tmp_html_file, 0644);

        $output = $this->get_review_html($attemptobj, $slots, $page, $showall, $lastpage);
        file_put_contents($tmp_html_file, $output);

        return array($tmp_html_file);
    }

    /**
     * Render the main page
     *
     * @param $attemptobj
     * @param $slots
     * @param $page
     * @param $showall
     * @param $lastpage
     * @return mixed
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function get_review_html($attemptobj, $slots, $page, $showall, $lastpage)
    {
        $html = $this->render($attemptobj, $slots, $page, $showall, $lastpage);
        return $html;
    }

    /**
     * Return the main page in which the quiz export settings can be configured.
     *
     * @param $attemptobj
     * @param $slots
     * @param $page
     * @param $showall
     * @param $lastpage
     * @return mixed
     * @throws coding_exception
     * @throws moodle_exception
     */
    protected function render($attemptobj, $slots, $page, $showall, $lastpage)
    {
        global $PAGE;

        $options = $attemptobj->get_display_options(true);

        // Ugly hack to get a new page
        $this->setup_new_page();

        $url = new moodle_url('/mod/quiz/report/export/a2pdf.php', array('attempt' => $attemptobj->get_attemptid()));
        $PAGE->set_url($url);

        // Set up the page header.
        // $headtags = $attemptobj->get_html_head_contributions($page, $showall);
        // $PAGE->set_title($attemptobj->get_quiz_name());
        // $PAGE->set_heading($attemptobj->get_course()->fullname);

        $summarydata = $this->summary_table($attemptobj, $options);

        // Display only content
        // $PAGE->force_theme('boost');
        $PAGE->set_pagelayout('embedded');

        $output = $PAGE->get_renderer('mod_quiz');

        // Fool out mod_quiz renderer:
        // 		set $page = 0 for showing comple summary table on every page
        // 			side effect: breaks next page links
        return $output->review_page($attemptobj, $slots, $page, $showall, $lastpage, $options, $summarydata);
    }

    /**
     * Generates a quiz review summary table.
     * The Code is original from mod/quiz/review.php and just wrapped to a function.
     *
     * @param quiz_attempt $attemptobj The attempt object the summary is for.
     * @param mod_quiz_display_options $options Extra options for the attempt.
     * @return array contains all table data for summary table
     */
    protected function summary_table($attemptobj, $options)
    {
        global $USER, $DB;

        // Work out some time-related things.
        $attempt = $attemptobj->get_attempt();
        $quiz = $attemptobj->get_quiz();
        $overtime = 0;

        if ($attempt->state == quiz_attempt::FINISHED) {
            if ($timetaken = ($attempt->timefinish - $attempt->timestart)) {
                if ($quiz->timelimit && $timetaken > ($quiz->timelimit + 60)) {
                    $overtime = $timetaken - $quiz->timelimit;
                    $overtime = format_time($overtime);
                }
                $timetaken = format_time($timetaken);
            } else {
                $timetaken = "-";
            }
        } else {
            $timetaken = get_string('unfinished', 'quiz');
        }

        // Prepare summary informat about the whole attempt.
        $summarydata = array();
        if (!$attemptobj->get_quiz()->showuserpicture && $attemptobj->get_userid() != $USER->id) {
            // If showuserpicture is true, the picture is shown elsewhere, so don't repeat it.
            $student = $DB->get_record('user', array('id' => $attemptobj->get_userid()));
            $usrepicture = new user_picture($student);
            $usrepicture->courseid = $attemptobj->get_courseid();
            $summarydata['user'] = array(
                'title' => $usrepicture,
                'content' => new action_link(new moodle_url('/user/view.php', array(
                    'id' => $student->id, 'course' => $attemptobj->get_courseid())),
                    fullname($student, true)),
            );
        }

        // Timing information.
        $summarydata['startedon'] = array(
            'title' => get_string('startedon', 'quiz'),
            'content' => userdate($attempt->timestart),
        );

        $summarydata['state'] = array(
            'title' => get_string('attemptstate', 'quiz'),
            'content' => quiz_attempt::state_name($attempt->state),
        );

        if ($attempt->state == quiz_attempt::FINISHED) {
            $summarydata['completedon'] = array(
                'title' => get_string('completedon', 'quiz'),
                'content' => userdate($attempt->timefinish),
            );
            $summarydata['timetaken'] = array(
                'title' => get_string('timetaken', 'quiz'),
                'content' => $timetaken,
            );
        }

        if (!empty($overtime)) {
            $summarydata['overdue'] = array(
                'title' => get_string('overdue', 'quiz'),
                'content' => $overtime,
            );
        }

        // Show marks (if the user is allowed to see marks at the moment).
        $grade = quiz_rescale_grade($attempt->sumgrades, $quiz, false);
        if ($options->marks >= question_display_options::MARK_AND_MAX && quiz_has_grades($quiz)) {

            if ($attempt->state != quiz_attempt::FINISHED) {
                // Cannot display grade.

            } else if (is_null($grade)) {
                $summarydata['grade'] = array(
                    'title' => get_string('grade', 'quiz'),
                    'content' => quiz_format_grade($quiz, $grade),
                );

            } else {
                // Show raw marks only if they are different from the grade (like on the view page).
                if ($quiz->grade != $quiz->sumgrades) {
                    $a = new stdClass();
                    $a->grade = quiz_format_grade($quiz, $attempt->sumgrades);
                    $a->maxgrade = quiz_format_grade($quiz, $quiz->sumgrades);
                    $summarydata['marks'] = array(
                        'title' => get_string('marks', 'quiz'),
                        'content' => get_string('outofshort', 'quiz', $a),
                    );
                }

                // Now the scaled grade.
                $a = new stdClass();
                $a->grade = html_writer::tag('b', quiz_format_grade($quiz, $grade));
                $a->maxgrade = quiz_format_grade($quiz, $quiz->grade);
                if ($quiz->grade != 100) {
                    $a->percent = html_writer::tag('b', format_float(
                        $attempt->sumgrades * 100 / $quiz->sumgrades, 0));
                    $formattedgrade = get_string('outofpercent', 'quiz', $a);
                } else {
                    $formattedgrade = get_string('outof', 'quiz', $a);
                }
                $summarydata['grade'] = array(
                    'title' => get_string('grade', 'quiz'),
                    'content' => $formattedgrade,
                );
            }
        }

        // Feedback if there is any, and the user is allowed to see it now.
        $feedback = $attemptobj->get_overall_feedback($grade);
        if ($options->overallfeedback && $feedback) {
            $summarydata['feedback'] = array(
                'title' => get_string('feedback', 'quiz'),
                'content' => $feedback,
            );
        }

        return $summarydata;
    }

    /**
     * Overwrites the $PAGE global with a new moodle_page instance.
     * Code is original from lib/setup.php and lib/adminlib.php
     *
     * @return void
     */
    protected function setup_new_page()
    {
        global $CFG, $PAGE;

        if (!empty($CFG->moodlepageclass)) {
            if (!empty($CFG->moodlepageclassfile)) {
                require_once($CFG->moodlepageclassfile);
            }
            $classname = $CFG->moodlepageclass;
        } else {
            $classname = 'moodle_page';
        }
        $PAGE = new $classname();
        unset($classname);

        $PAGE->set_context(null);
    }

    /**
     * Get student's firstname and lastname + quiz name
     * to display it at the top of the document
     *
     * @param quiz_attempt $attemptobj The attempt object the summary is for.
     * @return array contains additionnals informations
     */
    public function get_additionnal_informations($attemptobj)
    {
        global $DB;
        $user_id = $attemptobj->get_userid();
        $user_informations = $DB->get_record('user', ['id' => $user_id], 'firstname, lastname');
        return [
            'firstname' => $user_informations->firstname,
            'lastname' => $user_informations->lastname,
            'coursename' => $attemptobj->get_course()->fullname,
            'quizname' => $attemptobj->get_quiz_name()
        ];
    }

    /**
     * Encode all images in base64 to render it in the pdf
     *
     * @param $html
     * @return string|string[]
     */
    protected function preloadImageWithCurrentSession($html)
    {
        $matches = [];
        $matches_content = [];
        preg_match_all("/<img.*src=\"(https?:\/\/.*)\".*>/U", $html, $matches);

        if (count($matches[1]) > 0) {
            $cookieFile = '/tmp/cookie-pdf';
            file_put_contents($cookieFile, "MoodleSession=" . $_COOKIE['MoodleSession']);
            // Without that we have to wait the script eneded to load images => time out
            session_write_close();
            foreach ($matches[1] as $match) {
                $ch = curl_init($match);
                $strCookie = session_name() . '=' . $_COOKIE[session_name()] . '; path=/';
                curl_setopt($ch, CURLOPT_COOKIE, $strCookie);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_NOBODY, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                // Timeout in seconds
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                $header = curl_getinfo($ch);
                $result = curl_exec($ch);
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($result);
                $matches_content[] = "data:" . $mimeType . ";base64," . base64_encode($result);
                curl_close($ch);
            }
            $html = str_replace($matches[1], $matches_content, $html);
        }
        return $html;
    }
}

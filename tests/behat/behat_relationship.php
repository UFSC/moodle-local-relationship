<?php

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Step\Given as Given,
    Behat\Mink\Exception\ElementNotFoundException as ElementNotFoundException;

class behat_relationship extends behat_base {
    /**
     * Goes to specified URL
     * @Given /^I go to "([^"]*)"$/
     */
     public function iGoTo($url) {
         try{
             $session = $this->getSession();
             $session->visit($url);
         } catch(required_capability_exception $e){
             echo "Exceção: " . $e;
         }
     }

    /**
    * Click on the element with the provided xpath query
    *
    * @When /^I click on the element with xpath "([^"]*)"$/
    */
    public function iClickOnTheElementWithXPath($xpath)
    {
        $session = $this->getSession(); // get the mink session
        $element = $session->getPage()->find('xpath',
                   $session->getSelectorsHandler()->selectorToXpath('xpath', $xpath)); // runs the actual query and returns the element

        // errors must not pass silently
        if (null === $element) {
            throw new \InvalidArgumentException(sprintf('Could not evaluate XPath: "%s"', $xpath));
        }
        // ok, let's click on it
        $element->click();
    }

    /**
     * Navigate directly to the relationship index for the given category,
     * skipping the chain of UI clicks (Site home → Courses → category →
     * Relationships) that the Moodle 3.x admin layout no longer exposes
     * in a single label-stable path.
     *
     * @Given /^I am on the relationships page for category "([^"]*)"$/
     */
    public function iAmOnRelationshipsPageForCategory($categoryidentifier) {
        global $DB, $CFG;
        $category = $DB->get_record_select('course_categories',
                'name = :name OR idnumber = :idnumber',
                array('name' => $categoryidentifier, 'idnumber' => $categoryidentifier),
                '*', MUST_EXIST);
        $contextid = context_coursecat::instance($category->id)->id;
        $url = $CFG->wwwroot . '/local/relationship/index.php?contextid=' . $contextid;
        $this->getSession()->visit($url);
    }

    /**
     * Sets the component field of a relationship to mark it as externally
     * managed, so the UI hides the edit/delete buttons in index.php and
     * blocks edit_cohort/edit_group. Useful to test the "cantedit" branch
     * without installing a plugin that owns the relationship.
     *
     * @Given /^the relationship "([^"]*)" has component "([^"]*)"$/
     */
    public function theRelationshipHasComponent($name, $component) {
        global $DB;
        $relationship = $DB->get_record('relationship', array('name' => $name), '*', MUST_EXIST);
        $DB->set_field('relationship', 'component', $component, array('id' => $relationship->id));
    }

    /**
     * Bulk-creates N relationships in the given category by direct DB insert.
     * Used to push the listing over the 25-per-page boundary so pagination
     * scenarios can be exercised without driving the UI 26 times.
     *
     * Names are generated as "<prefix> <index>" with index starting at 1.
     *
     * @Given /^(\d+) relationships exist in category "([^"]*)" with prefix "([^"]*)"$/
     */
    public function nRelationshipsExistInCategoryWithPrefix($count, $categoryname, $prefix) {
        global $DB;
        $category = $DB->get_record('course_categories', array('name' => $categoryname), '*', MUST_EXIST);
        $context = context_coursecat::instance($category->id);
        $now = time();
        $total = (int) $count;
        $width = max(2, strlen((string) $total));
        for ($i = 1; $i <= $total; $i++) {
            $DB->insert_record('relationship', (object) array(
                'contextid' => $context->id,
                'name' => "{$prefix} " . str_pad((string) $i, $width, '0', STR_PAD_LEFT),
                'idnumber' => null,
                'description' => '',
                'descriptionformat' => FORMAT_HTML,
                'component' => '',
                'timecreated' => $now,
                'timemodified' => $now,
            ));
        }
    }

    /**
     * Attaches an enrol_relationship-like row so that index.php's
     * "Cursos que utilizam o relacionamento" branch is exercised without
     * installing the separate enrol_relationship plugin. Only the count and
     * the join in relationship_get_courses are exercised — the enrol method
     * itself does not have to be functional.
     *
     * @Given /^course "([^"]*)" uses the relationship "([^"]*)"$/
     */
    public function courseUsesTheRelationship($shortname, $relationshipname) {
        global $DB;
        $course = $DB->get_record('course', array('shortname' => $shortname), '*', MUST_EXIST);
        $relationship = $DB->get_record('relationship', array('name' => $relationshipname), '*', MUST_EXIST);
        $DB->insert_record('enrol', (object) array(
            'enrol' => 'relationship',
            'status' => 0,
            'courseid' => $course->id,
            'sortorder' => 0,
            'enrolperiod' => 0,
            'enrolstartdate' => 0,
            'enrolenddate' => 0,
            'expirynotify' => 0,
            'expirythreshold' => 0,
            'notifyall' => 0,
            'roleid' => 0,
            'customint1' => $relationship->id,
            'timecreated' => time(),
            'timemodified' => time(),
        ));
    }
}


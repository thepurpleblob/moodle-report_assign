moodle-report_assign
====================

Report to provide detailed information about Assignment submissions in a Course.

The original report was created to help identify users who have not submitted to an Assignment
when blind marking is enabled. It has now grown to have a number of other features.

Features are as follows...

* List all Assignments in a course and their basic status (e.g. blind marking, workflow)
* Show a table of submission data for all students within a selected Assignment. Additional identifying information can
be displayed to help identify non-submitting students
* Export the data for an Assignment to Excel.
* Export the data for all Assignments in the Course to a single Excel sheet
* Dump all submission files and all feedback (both files and comments) to a structured zip file (e.g. for archiving)

Admin settings are available to control what identifying data is included in the report (e.g. email address, id number)

Installation
============

* Unzip report into the report/ directory and rename to 'assign'
* Visit Site admin => Notifications.

Use
===

* To set the additional user fields go to Site administration > Plugins > Reports > Assignment submission report
* To use in a course, got to Settings cog > More... > Reports > Assignment submission report

History
======

* 1.2.0 - initial release
* 1.2.2 - Adds 'extension date' and 'due date' (to 'export all'). Unused strings removed
* 1.2.4 - Tested with versions 3.5 and 3.6
* 1.2.5 - show marking workflow status
* 1.2.6 - fix bug where it bombed if 'deletion in progress' assignments present

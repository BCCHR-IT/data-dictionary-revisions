# Data Dictionary Revisions
Allows you to compare any two revisions of the data dictionary **AFTER** the project has been moved to **PRODUCTION**. The external module will track fields modified, added, or deleted between revisions.

## Comparing Two Data Dictionary Revisions

### IMPORTANT: Make sure your study has been moved to production, and that you’ve submitted changes to the dictionary in production AT LEAST once. 

1. Once your changes have been approved and applied access the Data Dictionary Revisions plugin by accessing it through your sidebar menu under the External Modules section.

2. On the page, select two data dictionary revisions from the table.

3. Look at the Table of Changes for information about what fields have been modified, deleted, or added.

4. The User may download an Excel file that exports the Table of Changes, and a summary of details about the changes between version. You must have at least PHP 7.1 to do this. The module will hide the download link if PHP 7.0 is present. This is because BCCHR had multiple instances of REDCap across different PHP versions.

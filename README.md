# Bulk Importer

The Bulk Importer allows you to upload a zip archive of files to Symphony. The extracted
files are injected into a chosen section into an upload field. The name of the file is
added to the first instance of a "text input" style field. Optionally, you can link the
newly imported entries to another entry using either the Select Box Link, Reference Link
or Subsection Manager extensions.

- Version: 0.9.1
- Date: 15 November 2010
- Requirements: Symphony 2.0.8 or newer, <http://github.com/symphonycms/symphony-2/>
- Author: Brendan Abbott, brendan@bloodbone.ws
- GitHub Repository: <https://github.com/brendo/bulk-importer>

## INSTALLATION

1. Upload the 'bulkimporter' folder to your Symphony 'extensions' folder.
2. Enable it by selecting the "Bulk Importer", choose Enable from the with-selected menu, then click Apply.
3. You can now import files from the System -> Bulk Importer menu

## USAGE

1. Choose a zip file of files that you wish to upload
2. Select the section you want these files to be uploaded to and then select the upload field that
   these files should be uploaded to.
3. Based on the section you have chosen, the Bulk Importer looks for all related sections and asks
   if you would like to link these entries using one of these section links. Select the section link
   and then the entry that you would like the imported files to be associated with.

## USE CASE

I have an _Images_ section (Name - Textbox, Upload - Upload) and a _Gallery_ section (Name - Textbox,
Related Images - Subsection Manager). I choose the _Images_ section and then the Upload field to import
the files into. I then choose the _Gallery: Related Images_ section link and the entry named 'My Test Album'.
Clicking the 'import' button will upload the zip file, extract the files and then import them to the
_Images_ section. It will also associate these entries in the _Images_ section with the 'My Test Album' entry
in the _Gallery_ section.


## SUPPORTED FIELDS

### Upload fields

Any extension that matches the /upload/i regex (which is all known to date).

### Text Input fields

The textbox field extension and the core text input field are supported.

### Section Link fields

The Selectbox Link, Reference Link and Subsection Manager extensions.
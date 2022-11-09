TAR/GZIP/BZIP2/ZIP ARCHIVE CLASSES 2.0
By Devin Doucette
Copyright (c) 2004 Devin Doucette
Email: darksnoopy@shaw.ca

For anybody who has used previous versions of this script, there are virtually no 
similarities in the function calls so it would be a good idea to read this over or 
read through the source to see what the various functions do.  I will document the 
script as best as I can here, and list its limitations.

Requirements:
PHP 4 or greater (there is a chance tar and zip archives will work with PHP 3)
Compiled using --with-bz2 for bzip2 support.
Compiled using --with-zlib for gzip and zip support.
 (Zip archives created using method 0 do not require zlib)



Features:
Can create tar, gzip, bzip2, and zip archives.
Can create self-extracting zip archives.
Can recurse through and store directories.
Can create archives in memory or on disk.
Can allow client to download file straight from memory.
Errors are placed in an array named "errors" in the object.
Supports comments in zip files.
Supports special characters (up to ASCII 165) in filenames.
Files are automatically sorted within archive for greater 
   in gzip and bzip2 archives.
I could go on, but those are the major items.



Note:
Bzip2 and gzip archives are always created as tar files and then compressed, so the 
recommended file extensions are .tbz/.tbz2 or .tgz respectively.



Limitations:
Only USTAR archives are officially supported for extraction, but others may work.

Extraction of bzip2 and gzip archives is limited to compatible tar files that have 
been compressed by either bzip2 or gzip.  For greater support, use the functions 
bzopen and gzopen respectively for bzip2 and gzip extraction.

Zip extraction is not supported due to the wide variety of algorithms that may be 
used for compression and newer features such as encryption.  If you need to extract 
zip files, use the functions detailed at http://www.php.net/manual/en/ref.zip.php.

The download_file function only works for files that are stored in memory.  To 
redirect users to files that are on disk, redirect to the file, or use the following 
method: send the appropriate content-type header for the file being sent.
        send a "content-disposition: attachment; filename=[insert filename]" header.
        output the file contents.



Usage:
For tar use tar_file (eg. $example = new tar_file("example.tar");)
For gzip use gzip_file (eg. $example = new gzip_file("example.tgz");)
For bzip2 use bzip_file (eg. $example = new bzip_file("example.tbz");)
For zip use zip_file (eg. $example = new zip_file("example.zip");)

To set options, send an array containing the options that you wish to set to the 
function set_options. (eg. $example->setoptions($options);)
The options array can include any of the following:

basedir (default ".")
   sets the that all filenames are taken as being relative to (except sfx header)
   used both when creating and when extracting (will extract to basedir if not in memory)
name (no default)
   the name (and path, if necessary) of the archive, relative to basedir
   should be set when creating object (eg. $example = new zip_file("test/example.zip");)
prepend (no default)
   the path that is added to the beginning of every filename in the archive
inmemory (default 0)
   set to 1 to create/extract archive in memory, set to 0 to write to disk
overwrite (default 0)
   set to 1 to overwrite existing files when creating/extracting archives
   if set to 0, will give error message if file already exists
recurse (default 1)
   set to 1 to recurse through subdirectories, 0 to not recurse
storepaths (default 1)
   set to 1 to store paths in the archive, 0 to strip paths from the filenames
level (default 3, zip and gzip only) [1-9]
   level of compression for zip and gzip files, 0 is none
method (default 1, zip only)
   set to 1 to compress files in the zip archive, 0 to store files (no compression)
sfx (no default, zip only)
   filename of a valid sfx header for a zip archive, NOT relative to basedir
   the file zip.sfx from rarlabs.com, but another may be substituted
comment (no default)
   the comment added to a zip archive
   may be used to set options for some sfx modules, including the one provided

Example options array: $options = array('basedir'=>"../example",'overwrite'=>1);

To add files use the add_files function, which takes either an array or a single 
file/path.  The * character can be used but be careful, as it is the equivalent 
of placing .* in a regular expression.
Examples: $example->add_files("htdocs");
          $example->add_files(array("test.php","htdocs/*.txt"));
          $example->add_files("../*.gif");

To exclude files use the exclude_files function, which works the same as the 
add_files function, except it excludes any files that might otherwise be added to 
the archive. (eg. $example->exclude_files("*.html");)

To store files without compression (zip only), use the store_files function.
(eg. $example->store_files("htdocs/test.txt");)

To create an archive, use the create_archive function. (eg. $example->create_archive();)
The file created is the one passed when creating the object.  If the file is downloaded, 
the default filename for the download is the name passed when creating the object.

To extract an archive, use the extract_files function. (eg. $example->extract_files();)
The file extracted is the one passed when creating the object.  If the file is extracted 
to memory, the file information is located in an array called files (eg. $example->files)

The structure of the array into which files are extracted in memory is as follows:
$files = array(
'name'=>filename,
'stat'=>array(
   2=>mode
   4=>uid
   5=>gid
   7=>size
   9=>mtime),
'type'=>0 for file, 5 for directory,
'data'=>file contents);



Example of compression:
$test = new gzip_file("htdocs/test/test.tgz");
$test->set_options(array('basedir'=>"../..",'overwrite'=>1,'level'=>1));
$test->add_files("htdocs");
$test->exclude_files("htdocs/*.swf");
$test->store_files("htdocs/*.txt");
$test->create_archive();

Example of decompression:
$test = new gzip_file("test.tgz");
$test->set_options(array('overwrite'=>1));
$test->extract_files();



Please report any bugs to darksnoopy@shaw.ca.
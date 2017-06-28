Usage:
    spm help
    spm version
    spm list [<pattern>]
    spm install <id_name>[-<version>]
    spm uninstall <id_name>[-<version>]
    spm upload <id_name>[-<version>]
    spm remove <id_name>[-<version>]
    spm repair
    spm dbquery [<sql>]
    spm check
    spm file <file1> [<file2> ...]
    spm pack-create
    spm zip <id_name>[-<version>]
    spm sandbox-init
    spm sandbox-status
    spm sandbox-install
    spm md5-generate [<filename>]
    spm md5-compare <file1> [<file2>]

spm help
    Display this help. Can be executed outside of the SugarCRM.

spm version
    Display version. Can be executed outside of the SugarCRM.

spm list [<pattern>]
    List packages. Has different behavior outside of the SugarCRM and inside it.
    By default, list installed packages and loaded but not installed packages
    optionally filtered by <pattern>. List available packages if -a option used.
    Available packages are folders with sources (not zipped) located at spm_path.
    spm_path can be specified by environment variable SPM_PATH or option --spm-path.
    Available packages can be used for uploading to SugarCRM or just zipping.
    Manifest file will be automatically extracted if only zip files was uploaded.
    Options:
        -a - list available packages
        --each-version - print one line for each version
        --spm-path=<path> - search package sources on <path>

spm install <id_name>[-<version>]
    Install package. The package must be loaded to SugarCRM. Manifest file will be
    automatically extracted if only zip file was uploaded.
    Options:
        --no-copy - do not execute install procedure; just run pre_install and post_install scripts
        --lock-file=<file> - file used to lock installation/uninstallation, .spm.lock, by default
        --log-file=<file> - file used to log installation/uninstallation, spm.log, by default

spm uninstall <id_name>[-<version>]
    Uninstall package. By default, if doesn't remove tables, ACL, etc.
    Options:
        --remove-tables - remove bean tables
        --remove-acl - remove ACL actions
        --remove-custom - remove customization directory
        --remove-prefs - remove user preferences
        --remove-relationships - remove relationships (and modify viewdefs)
        --not-uninstallable - uninstall package even if it hasn't is_uninstallable attribute
        --no-copy - do not execute uninstall procedure; just run pre_uninstall
        --lock-file=<file> - file used to lock installation/uninstallation, .spm.lock, by default
        --log-file=<file> - file used to log installation/uninstallation, spm.log, by default

spm upload <id_name>[-<version>]
    Zip sources and upload zip archive and manifest to the SugarCRM upload directory.
    Sources must be among the available packages (see `spm list`).
    Also php syntax checked for all php files.
    Options:
        --no-php-check - skip php syntax check
        --spm-path=<path> - search package sources on <path>

spm remove <id_name>[-<version>]
    Remove package files from upload directory.

spm repair
    Run Quick Repair and Rebuild. Show SQL-queries if differences found between database and vardefs.
    Options:
        -v - show output

spm dbquery [<sql>]
    Run SQL-query on SugarCRM database. If sql not specified, standard input will be read.
    If .spmqueries.php file exists, it must return an array of allowed queries.
    In this case error will be thrown if executed query is not in the list.
    Options:
        -s - skip unallowed queries, i.e. do not throw exception when the query is not allowed
        -f - force execution of any query even if file .spmqueries.php exists and it not contains the query

spm check
    Search conflicts between installed packages.
    Options:
        --by-restore - also run conflict search based on files saved in *-restore folders
        -a - do not hide conflicts that resolved by `overwrite` attribute
        --modified - search for files that was added or modified but not in packages

spm file <file1> [<file2> ...]
    Try to search file(s) in the installed packages.
    Options:
        --sync - write file back to its package
        --spm-path=<path> - search package sources on <path> in sync command

spm pack-create
    Run interactive dialogue and then create folder with simple package structure.
    Can be executed outside of the SugarCRM.

spm zip <id_name>[-<version>]
    Create zip archive of package sources in the current directory.
    Can be executed outside of the SugarCRM.
    Sources must be among the available packages (see `spm list`).
    Options:
        --no-php-check - skip php syntax check
        --spm-path=<path> - search package sources on <path>

spm sandbox-init
    Create file .spmsandbox. After creating, this file should be manually edited
    to store information about which packages must be installed. While creating
    the file, all currently installed packages will be written to sandbox file
    commented by semicolon.
    Options:
        --no-merge - write to file not only last version of each package but every version installed earlier

spm sbinit
    This is an alias for `spm sandbox-init`.

spm sandbox-status [environment1 [environment2 ...]]
    Show difference between currently installed packages and sandbox file.
    Multiple environments separated by spaces may be depicted. By default,
    packages with empty environment used for building difference.
    Options:
        --file=<file> - path to sandbox file, use it to compare with other installation
        --input - use standard input instead of file

spm sbstatus
    This is an alias for `spm sandbox-status`.

spm sandbox-install [environment1 [environment2 ...]]
    Run installation of packages listed in `spm sandbox-status` (including reinstall section).
    Options:
        --file=<file> - path to sandbox file (if not default)
        --input - use standard input instead of file
        --no-uninstall - do not uninstall previous versions of installing packages
        + all options from `spm install` command
        + all options from `spm uninstall` command

spm sbinstall
    This is an alias for `spm sandbox-install`.

spm md5-generate [<filename>]
    Generate file with array of files md5 checksums. Format is like in Diagnostic Tool (MD5 Calculated array).
    If filename not specified, it will be generated.

spm md5-compare <file1> [<file2>]
    Compare arrays with md5 checksums. Files must contains checksums in format
    like in Diagnostic Tool (MD5 Calculated array) or like in file files.md5.
    If file2 not specified, current checksums array will be generated and used.


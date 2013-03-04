Namespacer
==========

This tool will assist you in taking older underscore/prefix spaced code
and will namespace it as best as it can, and also make the files and class
names ZF/PEAR compatible.

This two step command will first scan files and produce a map.  You can
then view or edit the file before moving onto the second step.  The
second step does the actual transformation.  These transformations will
be done in place, so it is best to either do this in a separate copy of
the code, or on top of a git repository where you can easily reset --hard
if you need to.

**Download**: https://github.com/ralphschindler/Namespacer/blob/master/namespacer.phar?raw=true

* First, create a map file:

    ```
    namespacer.phar map --mapfile types.php --source path/to/src
    ```

* Second, transform the types located in the map file:

    ```
    namespacer.phar transform --mapfile types.php
    ```

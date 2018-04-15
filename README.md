# phpmaildirclass

## Description

phpmaildirclass is a small demo written in the scripting language PHP. It allows you to view your mails
in your favorite browser. Multiple mail folders and user authentication by http (i.e. 'htpasswd') are
supported.

Where the mail data come from does not matter as long as it is located in your file system and is
readable by the webserver/php process. It must be stored in **Maildir** format easily recognisable by
its folder names 'cur' and 'new' and subfolders beginning with a dot. mbox (all mails in one file) is
unsupported.

*It supports no mail sending by SMTP or any comfort like addressbook, HTML formatting, PGP integration
and so on. It is read-only and does NOT grab your mails by POP3 or IMAP.*

## Requirements

* [PHP](http://www.php.net/). Versions less than 7.x are untested.

* A webserver. Either the PHP internal or another.

* A web browser capable of HTML5.

## Audience

Anyone interested in mail parsing or want to build a (read-only) webmail without mail server access.

You can adapt some variables in *pmdc-demo.php* to your needs to fit your environment. Anything beyond
the capabilities of this demo must be done on your own.

*pmdc-class.php* does the dirty work by reading the contents of a given filename and processes the header
and body data. The raw is split into chunks and computed one by one. "Meta data" like header lines, content
type or charset definitions are put directly on a stack where you can retrieve it. To save compute time
and memory body parts are not kept in memory but only byte offsets where they can be found in the raw. Some
resource limitations can be tweaked at the beginning of the class.

## License

All files are released to the [MIT License](https://github.com/AnanasPfirsichSaft/phpmaildirclass/blob/master/LICENSE).

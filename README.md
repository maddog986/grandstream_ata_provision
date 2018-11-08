# Grandstream ATA Provision

Since Grandsream does not make getting into their provision easy, or even get back to use to setup an account, I had to program this as an alternative. Many other companies out there have TR69 servers and other setups, but again, this is simple and controlled by us.

This uses a public webserver and PHP backend to create config files and provide a simple checkin system so we can make sure remote ATA devices are checking in and see how often.

We use it to control 20+ ATAs that our business has deployed out in the field.

Highly recommened to use an .htaccess and .htpasswd to lock down your entire provision folder.

To use, you must add your own config options and remove the .example extension from the files.

### Todo

- Move away from .json file to DB backend as overtime the file grows large with enough devices checking in to often.
- Build a web gui to be able to modify and change config files.
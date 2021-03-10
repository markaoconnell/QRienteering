# QRienteering
## What is this?
This is a set of software to create, run, and manage orienteering events where each control is identified by a unique QR code that is scanned by
the participant as "proof" that the control was visited.

### Why is this worthwhile?
- This allows meets to be set up and run with minimal cost - all that is needed is to print the QR codes and hang them (in the woods, in a park, on streets, etc)
- Full electronic timing is provided - splits are immediately available when the participant finishes, absolute time at each control is available
  - No need to purchase any expensive equipment in order to put on a simple meet
- Support for ScoreO format is available as well as normal (linear) courses
- Integration with SI unit timing is available, but takes a little more setup
- For clubs with defined members, there is an option for an optimized registration process which can recognize club members (see the `members.csv` file later)

### What are the disadvantages?
- Participants are required to have a smartphone which can scan QR codes, the smartphone must be able to access the web site, and there must be data connectivity at start, finish,
and at all controls
- The smartphone must accept cookies

## How do I use this?
The normal way to start is via the manage_events.php page, for which you should get a link from whoever has installed this software.  This is the one page from which most everything will be run.
The link will be of the form

> `website`/`software install diretory`/OMeetMgmt/manage_events.php?key=`user specific identifier`

e.g.:

> http://www.mysite.com/SimpleOrienteering/OMeetMgmt/manage_events.php?key=TestingArea

The manage_events.php will show a menu like this:
1. Create a new event
1. Manipulate an existing event
    1. Add new course to Auburndale Newton March 28, 2021 -- (create a copy of this event)
1. Get a registration link:
    1. Auburndale Newton March 28, 2021
        1. BYOM Registration
        1. Member meet Registration
        1. Non-member meet Registration
1. Get QR codes
    1. Get QR codes for Auburndale Newton March 28, 2021
1. Mass start an event
1. View recent results:
    1. Current events
        1. Results for Auburndale Newton March 28, 2021
            1. still on course
            1. Meet Director view of competitors
    1. Recently closed events
1. Winsplit files
    1. Download winsplits csv for Auburndale Newton March 28, 2021
1. Finish an event

#### Normal event flow
1. Using the `Create a new event` link, create a new event.
1. Using the `Get QR codes` section, get the QR codes for the event and print them.
1. Hang the controls in the proper locations
1. Hang the `start`, `finish`, and `BYOM register` QR codes in appropriate locations when starting the event.
1. Allow participants to run the event!
1. Use the `View recent results` to see how the particpants are doing.  The `Results for xxx` link shows completed participants.  The `still on course` shows participants
who are still on the courses and the last control that they visited.  The `Meet Director view of competitors` allows the meet director to remove invalid entrants, to modify
the split times of participants, to see a full list of punched controls for a competitor still on the course, and more.
    1. Note that the overall results and the `still on course` pages are available to ALL particpants (these are readonly), while the Meet Director view is more restricted.
1. When done, use the `Finish an event` link to mark the event as complete.  This will prevent further registrations for the event, but if there are still competitors on
the course, it will not interfere with their ability to complete the course.
1. If desired, download the splits file via `Winsplit files` and upload to winsplits, routegadget, or other orienteering sites.


## What are the minimum requirements?
A web server that runs php, minimally php 5.6 has been tested, though earlier versions of php may also work.
The software must be installed and it must have write access to the directory `OMeetData`

## What should I install?

The following directories should be installed on your web server, together with all the .php scripts in them.
Normally these should be put directory specific to this software, e.g. QR_code_orienteering, QRienteering, SimpleOrienteeringSoftware, or whatever you would like.
- OMeet
- OMeetCommon
- OMeetMgmt
- OMeetRegistration
- OMeetWithMemberList

In addition, you should create an empty directory `OMeetData` in this same directory.
OMeetData will hold all the information about the various orienteering courses, the results, etc.  This directory must be accessible / writeable by the software,
but does not need to be otherwise accessible to users of the web site.

These files / directories are in the github repo but do not need to be installed to make this work
 - These are used for testing while developing and are only of interest for contributors
   - test_run_competition
   - testing
- These are obsolete and will be removed from the repo
   - QR_codes.pptx
- This is the file you are looking at and is only needed to to understand what this software does
   - README.md

## How can I configure this?

### What are the configuration files:
#### Top level configuration
Top level (peer of OMeet, OMeetMgmt, and other directories)
 - keys
 - site_error_msg.txt
 - timezone.txt

##### keys
The keys file is what controls the multi-tenant nature of this software.  Each specific key should be associated with a distinct "user" of the software, such that each such "user"
has a directory in OMeetData where events and results specific to that user of the software - thus each "user" operates completely distinctly and independently from any other "user".
"User" could be an individual, a club (e.g. NEOC, BOK, CSU, etc), a series run by a club (e.g. CSU_Park_Series or NEOC_Winter_Trainings), a testing area (e.g. NEOC_Test), or whatever.

The format of the keys file is:
> externally visible key,directory in OMeetData,password (currently unused)

e.g.

```
NEOC_Testing,NEOC_Test_area,123456
NEOC_Spring2021,NEOC_BYOM_Spring2021_Meets,unused
ScoutOrienteeringMeritBadge,MeritBadge_courses,unused
```

This file must be present with at least one line to use the software.  The key must be used on the manage_events.php, e.g.

> `site`/`install_directory`/OMeetMgmt/manage_events.php?key=NEOC_Testing

So if the software is installed on www.myorienteeringsite.com under OrienteeringEvents, the link would be:

> http://www.myorienteeringsite.com/OrienteeringEvents/OMeetMgmt/manage_events.php?key=NEOC_Testing

##### site_error_msg.txt
This file contains HTML which will be shown when a QR code is scanned by an individual who is not currently registered for a course.  Ideally this contains some helpful
information and directs the person scanning the code to a web site for the club or about orienteering in general.

##### timezone.txt
This file contains the timezone which is assumed when displaying splits and the like.  The timezone should be formatted as per [https://www.php.net/manual/en/timezones.php](https://www.php.net/manual/en/timezones.php)
This can be overridden in the data area for a specific user if desired.


#### Per-user configuration
Per-Key (supports multi-tenant access to the SW)
 - club_name
 - email_extra_info.txt
 - email_properties.txt
 - member_waiver
 - members.csv
 - nicknames.csv
 - non_member_waiver
 - qr_code_footer.html


##### club_name
When running a meet with a pre-defined list of club members, this is the club name assumed when someone registers as a member, e.g. `NEOC` or `DVOA` or `COC`

##### email_properties.txt
This file contains information used to send splits by email (if desired).  If not present, then no emails will be sent.  Format is:
```
# Email properties file
# Format is property : value
# Lines beginning with # are ignored
# valid properties are from, reply-to (both required), subject (optional), include-splits (optional)
from : myemailaddress@myserver.com
reply-to : myreplyaddress@myserver.com
subject : Orienteering Meet Results
include-splits : 1
```

##### email_extra_info.txt
This file contains additional HTML which will be appended at the end of the email, normally to indicate the club's web site or upcoming event schedule.

##### member_waiver
This file contains additional HTML which will link to a waiver that members must accept to register for a meet.

##### members.csv
##### nicknames.csv

##### non_member_waiver
This file contains additional HTML which will link to a waiver that non-members must accept to register for a meet.

##### qr_code_footer.html
This file contains HTML which will be printed at the bottom of the QR codes when creating the QR codes as a web page.

##### timezone.txt
This allows the timezone to be set for an individual user of the software, as distinct from the overall default.



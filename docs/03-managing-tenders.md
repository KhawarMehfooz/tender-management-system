# Managing Tenders

_Last updated: 28/08/2026_

## Creating a tender

A tender is created through a guided, multi-step form. You can move between steps freely while filling it in, but every required field is checked when you save. The steps are:

1. **Basic info.** The tender's title, procurement number, contracting authority, procurement office, and contact details.
2. **Location & classification.** The service category the tender belongs to, its sector, procurement procedure, city, and its CPV and NUTS classification codes.
3. **Dates & deadlines.** The submission deadline, the bidder-question deadline, a site visit date, the publication date, and the bid validity period.
4. **Contract terms.** The estimated contract volume, contract term, contract start and end dates, and any extension options.
5. **Source & notes.** Where the tender was found, a link to the source portal, and free-text notes.

![The create-tender wizard, first step](screenshots/tenders-create.jpg)
*Creating a new tender.*

The estimated contract volume is only visible if your account has the right to see prices. If you don't have that right, the field is hidden entirely rather than shown blank, and your organization's administrator can grant it if you need it.

The wizard has one more step after these five, called **Team**, where you assign an owner and team members to the tender. That step is covered in [Team assignment](04-team-assignment.md).

## The tender lifecycle

Once created, a tender moves through a fixed sequence of stages:

**intake → review → decision → processing → quality → submission → follow-up**

ending in one of: **won, lost, cancelled, not evaluated,** or **excluded**.

A tender can only move to the next stage in the sequence. It can't skip a stage, and it can't move backward. The cancelled, not evaluated, and excluded outcomes are the exception: they can be reached from any active stage, since a tender can be dropped at any point. Won and lost can only be reached from the submission or follow-up stages, since a bid has to actually be submitted first.

To move a tender forward, open it from the tender list and use the **Change status** action. Choose the next stage from the list of stages currently allowed, and optionally add a reason.

![The tender list with the row action menu open, Change status highlighted](screenshots/change-status-menu-highlighted.jpg)
*The Change status action, on a tender's row menu.*

![The Change status modal, open with the next-stage options and a reason field](screenshots/change-status-menu-modal-open.jpg)
*Choosing the next stage and adding a reason.*

Every status change is recorded: who made it, when, what it changed from and to, and the reason if one was given. You can review this history on the tender's detail page, under **Status history**.

![A tender's detail page, including its status history](screenshots/tender-view-full-page.png)
*A tender's detail page, including its status history.*

## Archiving and invalidating

Tenders are never deleted outright. If a tender is no longer active or relevant, it's archived instead, and archiving keeps the full record intact. A tender can be archived at any point in its lifecycle, including after it's already been won or lost, since archiving is separate from the status flow above.

Use the **Archive** action on a tender to archive it, and **Unarchive** to bring it back.

If a tender turns out to be invalid, for example a duplicate or a listing that was withdrawn, it can be flagged as invalid instead. Flagging a tender invalid requires a reason, and the flag can be cleared later with **Clear invalid flag** if it turns out the tender is valid after all.

![A tender's detail page showing it as both archived and flagged invalid](screenshots/tender-view-archived-and-invalid.jpg)
*Archive and validity status on a tender's detail page.*

## Removing a genuine junk entry

In rare cases, such as a tender created by mistake with no real data attached to it, an administrator can permanently remove it. This is a separate, logged action available only to super admins, and it always requires a reason. It's meant for cleaning up genuine mistakes, not for tenders that are simply no longer active, which should be archived instead. See [Administration](08-administration.md) for more on admin-only actions.

## Where to go next

- [Team assignment](04-team-assignment.md), covering assigning an owner and team members to a tender.
- [Tasks](05-tasks.md), covering breaking a tender's work into tracked, assignable tasks.

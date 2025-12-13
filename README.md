# Lingua Translation Server

Consolidated translations for Survos projects.  Translations are submitted and processed by the survos/translation-bundle.

They are NOT stored in babel-bundle format, but there is a bridge to link babel-backed storage with Lingua.

## Database

![Database Diagram](assets/db.svg)

## Workflow

* Source strings are uploaded.
* Target entities are generated with marking=new
* TargetMessage is queued via messenger


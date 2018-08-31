#!/usr/bin/env bash

export OTGS_YT_URL=URL_TO_YOU_YOUTRACK_INSTANCE
export OTGS_YT_TOKEN=YOUR_YOUTRACK_ACCESSO_TOKEN
export OTGS_YT_ISSUES_LIMIT=500
export OTGS_YT_OUTPUT_FILE=./report.txt

./otgs-youtrack reports --filter="saved search: wpml-resolved-epics Assignee: andrea.s"
./otgs-youtrack reports --filter="saved search: wpml-resolved-epics Assignee: mihai.g" --overwrite-file=no
./otgs-youtrack reports --filter="saved search: wpml-resolved-epics Assignee: bruce.p" --overwrite-file=no
./otgs-youtrack reports --filter="saved search: wpml-resolved-epics Assignee: adriano.f" --overwrite-file=no
./otgs-youtrack reports --filter="saved search: wpml-resolved-epics Assignee: pierre.s" --overwrite-file=no
./otgs-youtrack reports --filter="saved search: wpml-resolved-epics Assignee: sergey.r" --overwrite-file=no
./otgs-youtrack reports --filter="saved search: wpml-resolved-epics Assignee: jakub.b" --overwrite-file=no

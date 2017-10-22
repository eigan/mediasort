## Tests

The code will always have 100% code coverage before any release. But that's often not enough,
so we also tests for additional scenarios.

##### General
- Source / Destination not exists
- Resolve source/destination with relative paths
- Resolve source/destination with relative paths (with `..`)
- Resolve home directory (`~`)
- Respecting interaction (yes/no)
- Respecting `--dry-run`

##### Permissions
###### Source
- Source not readable
- Nested source not readable
- Source file not readable

###### Destination
- Destination not writable
- Destination not readable
- Destination file not writable
- Destination sub directory not writable


##### File handling
- Move a single file
- Skip if duplicate
- `--only` extensions
- `--ignore` extensions
- `--type` (image/video/audio)
- Recursive and not recursive
- Increment with index
  - Check for duplicate after adding index

##### Formatters
- All formatters have correct values
- When formatter throws exception / crashes

##### Edge cases
- Source file removed right before moving it
- Move file across wrapper types (vfs:// to file://)
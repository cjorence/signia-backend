"""Copy a quiesced asset/media tree and verify every byte. No overwrite or archive extraction."""
import argparse
import hashlib
import json
import shutil
from pathlib import Path


def inventory(root):
    if not root.is_dir():
        raise ValueError('Backup source directory is missing.')
    result = {}
    for path in sorted(root.rglob("*")):
        if path.is_symlink():
            raise ValueError("Symlinks inside a backup tree are not supported.")
        if path.is_file() and "__pycache__" not in path.parts and path.suffix != ".pyc":
            with path.open('rb') as stream:
                result[path.relative_to(root).as_posix()] = {
                    "bytes": path.stat().st_size,
                    "sha256": hashlib.file_digest(stream, "sha256").hexdigest(),
                }
    return result


def verify(bundle):
    expected = json.loads((bundle / "manifest.json").read_text(encoding="utf-8"))
    if expected.get("version") != 1 or inventory(bundle / "files") != expected["files"]:
        raise ValueError("Backup verification failed: names, sizes or hashes differ.")
    return expected


def backup(source, destination):
    source, destination = source.resolve(), destination.resolve()
    if not source.is_dir() or destination == source or source in destination.parents:
        raise ValueError("Choose an existing source and a separate new backup destination.")
    before = inventory(source)
    destination.mkdir(parents=True, exist_ok=False)
    shutil.copytree(source, destination / "files", ignore=shutil.ignore_patterns("__pycache__", "*.pyc"))
    after = inventory(source)
    if before != after or before != inventory(destination / "files"):
        raise ValueError("Source changed during backup or copy was incomplete. Quiesce writers and retry in a new destination.")
    (destination / "manifest.json").write_text(json.dumps({"version": 1, "files": before}, indent=2), encoding="utf-8")
    verify(destination)
    return len(before)


def restore(bundle, destination):
    bundle, destination = bundle.resolve(), destination.resolve()
    verify(bundle)
    if destination.exists() or destination == bundle or bundle in destination.parents:
        raise ValueError("Restore requires a new isolated destination outside the backup.")
    shutil.copytree(bundle / "files", destination)
    if inventory(destination) != verify(bundle)["files"]:
        raise ValueError("Restored files failed verification.")


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("operation", choices=("backup", "verify", "restore"))
    parser.add_argument("source", type=Path)
    parser.add_argument("destination", type=Path, nargs="?")
    args = parser.parse_args()
    if args.operation != "verify" and args.destination is None:
        parser.error("destination is required")
    if args.operation == "backup":
        count = backup(args.source, args.destination)
        print(f"Verified backup: {count} files.")
    elif args.operation == "verify":
        print(f"Verified backup: {len(verify(args.source)['files'])} files.")
    else:
        restore(args.source, args.destination)
        print("Restored to a new isolated destination; hashes verified.")

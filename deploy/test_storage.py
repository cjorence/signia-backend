"""Exercise volume initialization with PHP, only in disposable temporary directories."""
import subprocess
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).with_name('initialize-volume.php')


class BackendStorageTests(unittest.TestCase):
    def run_init(self, root):
        return subprocess.run(['php', str(SCRIPT), str(root)], capture_output=True)

    def test_restarts_keep_media_and_incomplete_volumes_are_rejected(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            self.assertEqual(self.run_init(root).returncode, 0)
            media = root / 'media/app/stories/test.pck'
            media.write_bytes(b'preserve this story')
            self.assertEqual(self.run_init(root).returncode, 0)
            self.assertEqual(media.read_bytes(), b'preserve this story')
            (root / 'media/.signia-initialized.json').unlink()
            self.assertNotEqual(self.run_init(root).returncode, 0)
            self.assertEqual(media.read_bytes(), b'preserve this story')

    def test_interrupted_initialization_is_not_replaced(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            (root / '.signia-initializing').mkdir()
            self.assertNotEqual(self.run_init(root).returncode, 0)
            self.assertFalse((root / 'media').exists())


if __name__ == '__main__':
    unittest.main()

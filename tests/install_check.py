"""Check the private installation helper against a real disposable extracted package."""
import json, pathlib, subprocess, sys, tempfile, zipfile
with tempfile.TemporaryDirectory() as directory:
    root=pathlib.Path(directory)
    with zipfile.ZipFile(sys.argv[1]) as archive: archive.extractall(root)
    command=['php',str(root/'bin/check-installation.php')]
    good=subprocess.run(command,capture_output=True,check=True,text=True)
    result=json.loads(good.stdout)
    assert result['diskIntegrityVerified'] and result['sapi']=='cli'
    assert len(result['sourceRevision'])==40
    path=root/'src/runtime.php'
    path.write_bytes(path.read_bytes()+b'\n// Simulate a mixed deployment.\n')
    bad=subprocess.run(command,capture_output=True,text=True)
    assert bad.returncode==1
    assert json.loads(bad.stdout)['mismatchedFiles']==['src/runtime.php']
    (root/'deployment-manifest.json').unlink()
    legacy=subprocess.run(command,capture_output=True,text=True)
    assert legacy.returncode==1 and not json.loads(legacy.stdout)['diskIntegrityVerified']
print('Private installation helper detects mixed and unverifiable packages passed')

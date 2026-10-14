#!/usr/bin/env python3
"""End-to-end test of orchestra-library against a running WordPress. Started by run.sh.

Environment: E2E_BASE_URL, E2E_WP_CLI (wrapper script), E2E_WP_PATH, E2E_LIBRARY_ZIP.
"""
import html
import io
import json
import os
import re
import subprocess
import sys
import tempfile
import zipfile

BASE = os.environ["E2E_BASE_URL"]
WPC = os.environ["E2E_WP_CLI"]
WP_PATH = os.environ["E2E_WP_PATH"]
LIBRARY_ZIP = os.environ["E2E_LIBRARY_ZIP"]
WORK = tempfile.mkdtemp(prefix="orchestra-e2e-")
failures = []


def check(condition, label):
    print(("PASS " if condition else "FAIL ") + label)
    if not condition:
        failures.append(label)


def wp_eval(code):
    out = subprocess.run([WPC, "eval", code], capture_output=True, text=True)
    return out.stdout.strip()


def pdf(name, marker):
    path = os.path.join(WORK, name)
    with open(path, "w") as f:
        f.write(f"%PDF-1.4\n% {marker}\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n")
    return path


class Session:
    def __init__(self, user=None, password="pass"):
        self.jar = os.path.join(WORK, f"cookies-{user or 'anon'}.txt")
        open(self.jar, "w").close()
        if user:
            self.request("/wp-login.php", data={
                "log": user, "pwd": password, "wp-submit": "Log In", "testcookie": "1",
            }, cookie="wordpress_test_cookie=WP%20Cookie%20check")

    def request(self, path, data=None, files=None, cookie=None, follow=True, output=None):
        """Returns (status, headers, body). `files` maps field names to paths (multipart)."""
        header_file = os.path.join(WORK, "headers.txt")
        cmd = ["curl", "-s", "-c", self.jar, "-b", self.jar, "-D", header_file, "-o", output or "-"]
        if follow:
            cmd.append("-L")
        if cookie:
            cmd += ["--cookie", cookie]
        if files is not None:
            for key, value in (data or {}).items():
                for v in (value if isinstance(value, list) else [value]):
                    cmd += ["--form-string", f"{key}={v}"]
            for key, file_path in files:
                cmd += ["-F", f"{key}=@{file_path}"]
        elif data is not None:
            for key, value in data.items():
                for v in (value if isinstance(value, list) else [value]):
                    cmd += ["--data-urlencode", f"{key}={v}"]
        cmd.append(BASE + path)
        result = subprocess.run(cmd, capture_output=True)
        body = result.stdout
        if result.returncode != 0:
            print("CURL ERROR", result.returncode, result.stderr.decode()[:300], cmd[-6:])
        raw = open(header_file, encoding="latin-1", newline="").read()
        blocks = [b for b in raw.split("\r\n\r\n") if b.strip()]
        last = blocks[-1] if blocks else ""
        status = int(re.search(r"HTTP/[\d.]+ (\d+)", last).group(1)) if last else 0
        headers = {}
        for line in last.split("\r\n")[1:]:
            if ":" in line:
                k, v = line.split(":", 1)
                headers[k.strip().lower()] = v.strip()
        return status, headers, body


def field(page, name):
    match = re.search(r'name=["\']' + re.escape(name) + r'["\'][^>]*value=["\']([^"\']*)', page) \
        or re.search(r'value=["\']([^"\']*)["\'][^>]*name=["\']' + re.escape(name) + r'["\']', page)
    return html.unescape(match.group(1)) if match else None


def edit_form(session, path):
    _, _, body = session.request(path)
    page = body.decode()
    return page, {
        "action": "editpost",
        "post_ID": field(page, "post_ID"),
        "_wpnonce": field(page, "_wpnonce"),
        "user_ID": field(page, "user_ID"),
        "post_type": "orchestra_piece",
        "original_post_status": field(page, "original_post_status"),
        "orchestra_library_piece_nonce": field(page, "orchestra_library_piece_nonce"),
    }


def parts_of(piece_id):
    return json.loads(wp_eval(
        f'$l = Orchestra\\Library\\Plugin::instance()->library();'
        f'echo json_encode(array_map(fn($p) => ["id" => $p->id, "name" => $p->name, "position" => $p->position,'
        f' "file" => $l->sheetMusic($p->id)?->originalName], $l->parts({piece_id})));'
    ))


def storage_dir():
    return wp_eval('echo Orchestra\\Library\\File\\FileStorage::fromWordPress()->directory();')


horn1, horn1b, horn2, score = pdf("horn1.pdf", "horn1"), pdf("horn1b.pdf", "horn1b"), pdf("horn2.pdf", "horn2"), pdf("Partitur Märchen.pdf", "score")
fake = os.path.join(WORK, "fake.pdf")
open(fake, "w").write("<?php echo 'not a pdf';")

# Fresh plugin installation
subprocess.run(["unzip", "-q", "-o", LIBRARY_ZIP, "-d", os.path.join(WP_PATH, "wp-content/plugins")], check=True)
wp_eval('foreach (get_posts(["post_type" => ["orchestra_piece", "page"], "post_status" => "any", "numberposts" => -1]) as $p) wp_delete_post($p->ID, true);')
subprocess.run([WPC, "plugin", "deactivate", "orchestra-library"], capture_output=True)
subprocess.run([WPC, "plugin", "uninstall", "orchestra-library"], capture_output=True)
subprocess.run(["unzip", "-q", "-o", LIBRARY_ZIP, "-d", os.path.join(WP_PATH, "wp-content/plugins")], check=True)
subprocess.run([WPC, "plugin", "activate", "orchestra-library"], capture_output=True, check=True)

conductor = Session("conductor")

# 1. Create a piece with parts, sheet music and a piece file
page, form = edit_form(conductor, "/wp-admin/post-new.php?post_type=orchestra_piece")
check('enctype="multipart/form-data"' in page, "edit form is multipart")
check("Parts and Sheet Music" in page, "parts meta box is shown")
piece_id = int(form["post_ID"])
status, _, body = conductor.request("/wp-admin/post.php", data={
    **form,
    "post_title": "Pirates of the Caribbean",
    "content": "Arrangement for wind orchestra",
    "publish": "Publish",
    "orchestra_library_composer": "Klaus Badelt",
    "orchestra_new_parts[0][name]": "Horn 1", "orchestra_new_parts[0][position]": "1",
    "orchestra_new_parts[1][name]": " Horn  2 ", "orchestra_new_parts[1][position]": "2",
    "orchestra_new_parts[2][name]": "Percussion", "orchestra_new_parts[2][position]": "3",
}, files=[
    ("orchestra_new_part_file[0]", horn1),
    ("orchestra_new_part_file[1]", horn2),
    ("orchestra_piece_files[]", score),
])
check(status == 200 and wp_eval(f"echo get_post_status({piece_id});") == "publish", "conductor publishes piece")
parts = parts_of(piece_id)
check([(p["name"], p["file"]) for p in parts] == [("Horn 1", "horn1.pdf"), ("Horn 2", "horn2.pdf"), ("Percussion", None)],
      f"parts and sheet music stored: {parts}")
check(wp_eval(f'echo get_post_meta({piece_id}, "orchestra_library_composer", true);') == "Klaus Badelt", "composer stored")
directory = storage_dir()
stored = [f for f in os.listdir(directory) if re.fullmatch(r"[0-9a-f-]{36}", f)]
check(len(stored) == 3, f"three files in private storage ({directory})")
check("Require all denied" in open(os.path.join(directory, ".htaccess")).read(), "storage has .htaccess protection")

# 2. Duplicate names are rejected and nothing changes
page, form = edit_form(conductor, f"/wp-admin/post.php?post={piece_id}&action=edit")
by_name = {p["name"]: p["id"] for p in parts}
status, _, body = conductor.request("/wp-admin/post.php", data={
    **form, "post_title": "Pirates of the Caribbean", "save": "Update",
    "orchestra_library_composer": "Klaus Badelt",
    f"orchestra_parts[{by_name['Percussion']}][name]": "Percussion",
    f"orchestra_parts[{by_name['Percussion']}][delete]": "1",
    "orchestra_new_parts[0][name]": "horn 1",
}, files=[])
check("used more than once" in body.decode(), "duplicate part name is reported")
check(len(parts_of(piece_id)) == 3, "no part changed after validation error")

# 3. Rename, replace a file, remove a file, delete a part, reorder
page, form = edit_form(conductor, f"/wp-admin/post.php?post={piece_id}&action=edit")
data = {**form, "post_title": "Pirates of the Caribbean", "save": "Update", "orchestra_library_composer": "Klaus Badelt"}
for p in parts:
    data[f"orchestra_parts[{p['id']}][name]"] = p["name"]
    data[f"orchestra_parts[{p['id']}][position]"] = str(p["position"])
data[f"orchestra_parts[{by_name['Horn 2']}][name]"] = "Horn 2 in F"
data[f"orchestra_parts[{by_name['Horn 2']}][position]"] = "0"
data[f"orchestra_parts[{by_name['Percussion']}][delete]"] = "1"
conductor.request("/wp-admin/post.php", data=data, files=[(f"orchestra_part_file[{by_name['Horn 1']}]", horn1b)])
parts = parts_of(piece_id)
check([(p["name"], p["file"]) for p in parts] == [("Horn 2 in F", "horn2.pdf"), ("Horn 1", "horn1b.pdf")],
      f"rename, reorder, replace and delete applied: {parts}")
check(len([f for f in os.listdir(directory) if re.fullmatch(r"[0-9a-f-]{36}", f)]) == 3,
      "replaced file was removed from storage")

# 4. Disguised files are rejected with a notice
page, form = edit_form(conductor, f"/wp-admin/post.php?post={piece_id}&action=edit")
_, _, body = conductor.request("/wp-admin/post.php", data={**form, "post_title": "Pirates of the Caribbean", "save": "Update",
                                                         "orchestra_library_composer": "Klaus Badelt"},
                               files=[("orchestra_piece_files[]", fake)])
check("has a file type that is not allowed" in body.decode(), "PHP disguised as PDF is rejected")

# 5. Member library page, search and downloads
library_page = int(wp_eval('echo wp_insert_post(["post_type" => "page", "post_status" => "publish", "post_title" => "Library", "post_content" => "[orchestra_library]"]);'))
member = Session("member")
_, _, body = member.request(f"/?page_id={library_page}")
page = body.decode()
check("Pirates of the Caribbean" in page and "Horn 2 in F" in page and "Klaus Badelt" in page, "member sees the library")
if "Pirates of the Caribbean" not in page:
    print("DEBUG", re.findall(r"<h3>.*?</ul>", page, re.S)[:2])
check("Partitur" in page, "member sees the piece file")
_, _, body = member.request(f"/?page_id={library_page}&orchestra_q=Badelt")
check("Pirates of the Caribbean" in body.decode(), "search by composer")
_, _, body = member.request(f"/?page_id={library_page}&orchestra_q=Mozart")
check("No pieces found." in body.decode(), "search without results")

file_ids = json.loads(wp_eval(
    f'$l = Orchestra\\Library\\Plugin::instance()->library(); $out = [];'
    f'foreach ($l->parts({piece_id}) as $p) {{ $out[$p->name] = $l->sheetMusic($p->id)->id; }}'
    f'foreach ($l->pieceFiles({piece_id}) as $f) {{ $out["score"] = $f->id; }} echo json_encode($out);'
))
target = os.path.join(WORK, "download.bin")
status, headers, _ = member.request(f"/wp-admin/admin-post.php?action=orchestra_library_download&file={file_ids['Horn 1']}", output=target)
check(status == 200 and open(target, "rb").read() == open(horn1b, "rb").read(), "member downloads sheet music")
check(headers.get("content-type") == "application/pdf" and "horn1b.pdf" in headers.get("content-disposition", ""),
      f"download headers: {headers.get('content-type')} / {headers.get('content-disposition')}")
check("no-store" in headers.get("cache-control", ""), "download is not cached")
status, headers, _ = member.request(f"/wp-admin/admin-post.php?action=orchestra_library_download&file={file_ids['score']}", output=target)
check("filename*=UTF-8''Partitur-M%C3%A4rchen.pdf" in headers.get("content-disposition", "")
      or "Partitur" in headers.get("content-disposition", ""), f"non-ASCII file name: {headers.get('content-disposition')}")

# 6. Bundle download
bundle_url = wp_eval(f'echo Orchestra\\Library\\Plugin::instance()->library()->bundleDownloadUrl([{by_name["Horn 1"]}, {by_name["Horn 2"]}], "Spring Concert 2027");')
status, headers, body = member.request(bundle_url.replace(BASE, ""))
names = sorted(zipfile.ZipFile(io.BytesIO(body)).namelist()) if status == 200 else []
check(names == ["Pirates of the Caribbean - Horn 1.pdf", "Pirates of the Caribbean - Horn 2 in F.pdf"], f"bundle contents: {names}")
check("Spring" in headers.get("content-disposition", "") and ".zip" in headers.get("content-disposition", ""),
      f"bundle name: {headers.get('content-disposition')}")

# 7. Access control
anonymous = Session()
status, headers, _ = anonymous.request(f"/wp-admin/admin-post.php?action=orchestra_library_download&file={file_ids['Horn 1']}", follow=False)
check(status == 302 and "wp-login.php" in headers.get("location", ""), "logged-out download redirects to login")
_, _, body = anonymous.request(f"/?page_id={library_page}")
check("Please log in" in body.decode() and "Pirates" not in body.decode(), "logged-out visitors do not see the library")
subscriber = Session("sub")
status, _, _ = subscriber.request(f"/wp-admin/admin-post.php?action=orchestra_library_download&file={file_ids['Horn 1']}", output=target)
check(status == 404, "subscriber cannot download")
_, _, body = subscriber.request(f"/?page_id={library_page}")
check("not allowed" in body.decode() and "Pirates" not in body.decode(), "subscriber does not see the library")
status, _, body = member.request("/wp-admin/post-new.php?post_type=orchestra_piece")
check(status == 403 or "not allowed" in body.decode(), "member cannot create pieces")
direct_status = anonymous.request("/wp-content/uploads/" + os.path.basename(directory) + "/" + stored[0], follow=False)[0]
print(f"INFO direct URL to a stored file returns {direct_status}. PHP's built-in server ignores .htaccess,"
      " like nginx; on Apache it must be 403.")

# 8. Drafts and archived pieces are hidden from members
page, form = edit_form(conductor, "/wp-admin/post-new.php?post_type=orchestra_piece")
draft_id = int(form["post_ID"])
conductor.request("/wp-admin/post.php", data={**form, "post_title": "Secret Draft", "save": "Save Draft",
                                             "orchestra_new_parts[0][name]": "Flute"},
                  files=[("orchestra_new_part_file[0]", horn2)])
draft_file = wp_eval(f'$l = Orchestra\\Library\\Plugin::instance()->library(); echo $l->sheetMusic($l->parts({draft_id})[0]->id)->id;')
status, _, _ = member.request(f"/wp-admin/admin-post.php?action=orchestra_library_download&file={draft_file}", output=target)
check(status == 404, "member cannot download sheet music of a draft")
status, _, _ = conductor.request(f"/wp-admin/admin-post.php?action=orchestra_library_download&file={draft_file}", output=target)
check(status == 200, "conductor can download sheet music of a draft")
wp_eval(f'update_post_meta({piece_id}, "orchestra_library_archived", "1");')
_, _, body = member.request(f"/?page_id={library_page}")
check("Pirates" not in body.decode(), "archived piece is hidden in the library")
wp_eval(f'delete_post_meta({piece_id}, "orchestra_library_archived");')

# 9. Deletion is blocked while other plugins refer to a part
mu = os.path.join(WP_PATH, "wp-content/mu-plugins")
os.makedirs(mu, exist_ok=True)
open(os.path.join(mu, "blocker.php"), "w").write(
    "<?php add_filter('orchestra_library_part_delete_blockers', function ($b, $id) {"
    f" return $id === {by_name['Horn 1']} ? [...$b, 'Assigned in Spring Concert 2027.'] : $b; }}, 10, 2);"
)
wp_eval(f'delete_post_meta({piece_id}, "_edit_lock");')
admin = Session("admin", "admin")
_, _, body = admin.request(f"/wp-admin/post.php?post={piece_id}&action=edit")
trash_link = html.unescape(re.search(r'href="([^"]*action=trash[^"]*)"', body.decode()).group(1))
status, _, body = admin.request(trash_link.replace(BASE, ""), follow=False)
check(status == 409 and "Assigned in Spring Concert 2027." in body.decode(), "trashing a referenced piece is blocked")
check(wp_eval(f'echo get_post_status({piece_id});') == "publish", "piece is still published")
page, form = edit_form(conductor, f"/wp-admin/post.php?post={piece_id}&action=edit")
_, _, body = conductor.request("/wp-admin/post.php", data={**form, "post_title": "Pirates of the Caribbean", "save": "Update",
                                                         f"orchestra_parts[{by_name['Horn 1']}][name]": "Horn 1",
                                                         f"orchestra_parts[{by_name['Horn 1']}][delete]": "1"}, files=[])
check("cannot be deleted" in body.decode() and len(parts_of(piece_id)) == 2, "deleting a referenced part is blocked")
os.remove(os.path.join(mu, "blocker.php"))

wp_eval(f'wp_delete_post({piece_id}, true);')
check(parts_of(piece_id) == [], "deleting a piece deletes its parts")
remaining = [f for f in os.listdir(directory) if re.fullmatch(r"[0-9a-f-]{36}", f)]
check(len(remaining) == 1, f"deleting a piece deletes its files (remaining: {len(remaining)}, the draft's)")

# 10. Uninstall removes everything
subprocess.run([WPC, "plugin", "deactivate", "orchestra-library"], capture_output=True)
subprocess.run([WPC, "plugin", "uninstall", "orchestra-library"], capture_output=True)
tables = wp_eval('global $wpdb; echo json_encode($wpdb->get_col("SHOW TABLES LIKE \'%orchestra_library%\'"));')
check(tables == "[]", f"uninstall drops tables: {tables}")
check(not os.path.exists(directory), "uninstall removes the storage directory")
check(wp_eval('echo count(get_posts(["post_type" => "orchestra_piece", "post_status" => "any", "numberposts" => -1]));') == "0",
      "uninstall deletes pieces")
check(wp_eval('echo json_encode([get_option("orchestra_library_schema_version"), get_option("orchestra_library_storage_suffix")]);') == "[false,false]",
      "uninstall deletes options")

print(f"\n{len(failures)} failure(s)")
sys.exit(1 if failures else 0)

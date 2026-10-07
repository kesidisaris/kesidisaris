#!/usr/bin/env python3
"""Generates Elementor (free) page templates for the gastro/hepatology clinic."""
import json, os, random, re, sys

OUT = sys.argv[1]
LANG = sys.argv[2] if len(sys.argv) > 2 else "el"   # el | en | de | collect
os.makedirs(OUT, exist_ok=True)

_GREEK = re.compile(r"[\u0370-\u03ff\u1f00-\u1fff]")
COLLECTED = []
MISSING = []
D = {}
if LANG in ("en", "de"):
    sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
    import importlib
    D = importlib.import_module("tr_" + LANG).D


def tr(s):
    """Translate a Greek source string. Strings without Greek pass through unchanged."""
    if LANG == "el" or not _GREEK.search(s):
        return s
    if LANG == "collect":
        if s not in COLLECTED:
            COLLECTED.append(s)
        return _GREEK.sub("x", s)
    if s not in D:
        MISSING.append(s)
        return s
    return D[s]

rnd = random.Random(42)

NAVY = "#1A3153"
SAND = "#C5B598"
SAND_LIGHT = "#F4F0E8"
WHITE = "#FFFFFF"
TEXT = "#2B3445"
HEAD_FONT = "Noto Serif"
BODY_FONT = "Noto Sans"

MAPS = "https://maps.app.goo.gl/SZfedq1xMVoHa2SJ8"
INSTA = "https://www.instagram.com/haris_eftaxias/"
TEL1, TEL1_TXT = "tel:+302373025770", "23730 25770"
TEL2, TEL2_TXT = "tel:+306972931794", "697 293 1794"
BASE = ""  # relative links; pages must use these slugs
LINKS = {
    "el": ("/", "/o-iatros/", "/ypiresies/", "/pathiseis/", "/epikoinonia/"),
    "en": ("/en/", "/en/the-doctor/", "/en/services/", "/en/conditions/", "/en/contact/"),
    "de": ("/de/", "/de/arzt/", "/de/leistungen/", "/de/erkrankungen/", "/de/kontakt/"),
}
L_HOME, L_DOC, L_SERV, L_COND, L_CONT = LINKS.get(LANG, LINKS["el"])


def uid():
    return "%08x" % rnd.getrandbits(32)


def px(n):
    return {"unit": "px", "size": n}


def box(t, r=None, b=None, l=None):
    r = t if r is None else r
    b = t if b is None else b
    l = r if l is None else l
    return {"unit": "px", "top": str(t), "right": str(r), "bottom": str(b), "left": str(l), "isLinked": False}


def typo(size=None, size_m=None, weight=None, family=BODY_FONT, lh=None, prefix="typography"):
    d = {prefix + "_typography": "custom", prefix + "_font_family": family}
    if size:
        d[prefix + "_font_size"] = px(size)
    if size_m:
        d[prefix + "_font_size_mobile"] = px(size_m)
    if weight:
        d[prefix + "_font_weight"] = str(weight)
    if lh:
        d[prefix + "_line_height"] = {"unit": "em", "size": lh}
    return d


def widget(wtype, settings):
    return {"id": uid(), "elType": "widget", "settings": settings, "elements": [], "widgetType": wtype}


def link(url, ext=False):
    return {"url": url, "is_external": "on" if ext else "", "nofollow": "", "custom_attributes": ""}


# ---------- widgets ----------
def heading(text, tag="h2", color=NAVY, size=38, size_m=28, align="left", weight=700, family=HEAD_FONT, mb=16):
    s = {"title": tr(text), "header_size": tag, "align": align, "title_color": color,
         "_margin": box(0, 0, mb, 0)}
    s.update(typo(size, size_m, weight, family, 1.25))
    return widget("heading", s)


def text(html, color=TEXT, size=17, align="left", mb=14):
    s = {"editor": tr(html), "text_color": color, "align": align, "_margin": box(0, 0, mb, 0)}
    s.update(typo(size, 16, 400, BODY_FONT, 1.75))
    return widget("text-editor", s)


def button(label, url, bg=NAVY, color=WHITE, outline=False, ext=False, align="left", icon=None):
    s = {"text": tr(label), "link": link(url, ext), "align": align, "size": "md",
         "button_text_color": color, "background_color": bg,
         "button_background_hover_color": SAND if not outline else WHITE,
         "hover_color": NAVY,
         "border_radius": box(50, 50, 50, 50), "text_padding": box(16, 32, 16, 32),
         "_margin": box(0, 8, 8, 0)}
    s.update(typo(16, None, 600))
    if outline:
        s.update({"border_border": "solid", "border_width": box(2, 2, 2, 2), "border_color": color,
                  "background_color": "rgba(255,255,255,0)"})
    if icon:
        s["selected_icon"] = {"value": icon, "library": "fa-solid"}
        s["icon_align"] = "left"
        s["icon_indent"] = px(10)
    return widget("button", s)


def icon_box(icon, title, desc, url=None, color=NAVY, bg=WHITE, align="center"):
    s = {"selected_icon": {"value": icon, "library": "fa-solid"}, "view": "stacked",
         "shape": "circle", "primary_color": color, "secondary_color": WHITE,
         "icon_size": px(30), "icon_padding": px(26),
         "title_text": tr(title), "description_text": tr(desc), "position": "top", "title_size": "h3",
         "text_align": align, "title_color": NAVY, "description_color": TEXT,
         "title_bottom_space": px(10)}
    s.update(typo(21, None, 700, HEAD_FONT, None, "title_typography"))
    s.update(typo(16, None, 400, BODY_FONT, 1.6, "description_typography"))
    if url:
        s["link"] = link(url)
    return widget("icon-box", s)


def icon_list(items, color=NAVY, size=17, icon="fas fa-check-circle", space=10, text_color=TEXT):
    lst = []
    for it in items:
        if isinstance(it, tuple):
            t, u = it
            entry = {"text": tr(t), "selected_icon": {"value": icon, "library": "fa-solid"}, "_id": uid()[:7],
                     "link": link(u)}
        else:
            entry = {"text": tr(it), "selected_icon": {"value": icon, "library": "fa-solid"}, "_id": uid()[:7]}
        lst.append(entry)
    s = {"icon_list": lst, "space_between": px(space), "icon_color": color, "text_color": text_color,
         "icon_size": px(16)}
    s.update(typo(size, 16, 400, BODY_FONT, None, "icon_typography"))
    return widget("icon-list", s)


def accordion(tabs, open_first=False):
    items = [{"tab_title": tr(t), "tab_content": tr(c), "_id": uid()[:7]} for t, c in tabs]
    s = {"tabs": items, "selected_icon": {"value": "fas fa-plus", "library": "fa-solid"},
         "selected_active_icon": {"value": "fas fa-minus", "library": "fa-solid"},
         "title_color": NAVY, "tab_active_color": SAND, "icon_color": NAVY, "icon_active_color": SAND,
         "border_color": SAND, "content_color": TEXT, "title_html_tag": "h3"}
    s.update(typo(18, None, 700, HEAD_FONT, None, "title_typography"))
    s.update(typo(16, None, 400, BODY_FONT, 1.7, "content_typography"))
    return widget("accordion", s)


def image(url="", ratio=None, radius=16):
    s = {"image": {"url": url, "id": ""}, "image_size": "large", "align": "center",
         "image_border_radius": box(radius, radius, radius, radius)}
    return widget("image", s)


def divider(color=SAND, width=60, weight=3, align="left"):
    return widget("divider", {"style": "solid", "weight": px(weight), "color": color, "width": {"unit": "px", "size": width},
                              "align": align, "gap": px(8)})


def spacer(h=20):
    return widget("spacer", {"space": px(h)})


def gmap(address, h=380):
    return widget("google_maps", {"address": tr(address), "zoom": px(15), "height": px(h)})


def social(items):
    lst = [{"social_icon": {"value": ic, "library": "fa-brands"}, "link": link(u, True), "_id": uid()[:7]}
           for ic, u in items]
    return widget("social-icons", {"social_icon_list": lst, "shape": "circle", "icon_color": "custom",
                                   "icon_primary_color": NAVY, "icon_secondary_color": WHITE,
                                   "icon_size": px(22), "icon_padding": px(14)})


# ---------- containers ----------
def container(children, direction="column", bg=None, pad=None, pad_m=None, gap=24, width=None, inner=False,
              cid=None, align=None, justify=None, wrap=None, radius=None, shadow=False, boxed=True,
              min_h=None, full=False):
    s = {"flex_direction": direction, "flex_gap": {"unit": "px", "size": gap, "column": str(gap), "row": str(gap),
                                                    "isLinked": True}}
    if direction == "row":
        s["flex_direction_mobile"] = "column"
        s["flex_wrap"] = wrap or "wrap"
    if not inner:
        s["content_width"] = "boxed" if boxed else "full"
        if boxed:
            s["boxed_width"] = px(1180)
    if bg:
        s["background_background"] = "classic"
        s["background_color"] = bg
    if pad is not None:
        s["padding"] = pad
    if pad_m is not None:
        s["padding_mobile"] = pad_m
    if width:
        s["width"] = {"unit": "%", "size": width}
        s["width_mobile"] = {"unit": "%", "size": 100}
        s["width_tablet"] = {"unit": "%", "size": 100 if width > 33 else 50}
    if cid:
        s["_element_id"] = cid
    if align:
        s["flex_align_items"] = align
    if justify:
        s["flex_justify_content"] = justify
    if radius:
        s["border_radius"] = box(radius, radius, radius, radius)
    if shadow:
        s["box_shadow_box_shadow_type"] = "yes"
        s["box_shadow_box_shadow"] = {"horizontal": 0, "vertical": 8, "blur": 30, "spread": 0,
                                       "color": "rgba(26,49,83,0.12)"}
    if min_h:
        s["min_height"] = px(min_h)
    return {"id": uid(), "elType": "container", "settings": s, "elements": children, "isInner": inner}


def section(children, bg=None, cid=None, top=90, bottom=90, direction="column", gap=28, align=None):
    return container(children, direction=direction, bg=bg, pad=box(top, 20, bottom, 20),
                     pad_m=box(55, 20, 55, 20), cid=cid, gap=gap, align=align)


def cols(*children_lists, widths=None, gap=40, align="center", card=False):
    n = len(children_lists)
    widths = widths or [100 // n] * n
    inner = [container(ch, width=w, inner=True, gap=16, align=None,
                       bg=WHITE if card else None,
                       pad=box(32, 28, 32, 28) if card else None,
                       radius=18 if card else None, shadow=card)
             for ch, w in zip(children_lists, widths)]
    return container(inner, direction="row", inner=True, gap=gap, align=align, wrap="wrap")


def eyebrow(t, color=SAND):
    s = {"title": tr(t), "header_size": "p", "title_color": color, "align": "left", "_margin": box(0, 0, 6, 0)}
    s.update(typo(14, None, 700, BODY_FONT, None))
    s["typography_letter_spacing"] = {"unit": "px", "size": 2}
    s["typography_text_transform"] = "uppercase"
    return widget("heading", s)


def template(title, content, ttype="page"):
    return {"title": tr(title), "type": ttype, "version": "0.4", "page_settings": [], "content": content}


def save(name, tpl):
    with open(os.path.join(OUT, name), "w", encoding="utf-8") as f:
        json.dump(tpl, f, ensure_ascii=False, indent=1)


def page_hero(kicker, title, lead=None):
    ch = [eyebrow(kicker), heading(title, "h1", WHITE, 46, 30, mb=12)]
    if lead:
        ch.append(text(lead, "#E8E2D4", 18))
    return container(ch, bg=NAVY, pad=box(80, 20, 70, 20), pad_m=box(50, 20, 45, 20), gap=8)


CALL_BTNS = lambda align="left": [
    button(tr("Κλείστε ραντεβού") + ": " + TEL1_TXT, TEL1, SAND, NAVY, icon="fas fa-phone-alt", align=align),
    button(tr("Απευθείας με τον ιατρό") + ": " + TEL2_TXT, TEL2, outline=True, color=WHITE, align=align),
]


def cta_band():
    return container([
        heading("Κλείστε το ραντεβού σας", "h2", WHITE, 34, 26, "center"),
        text("Το ιατρείο λειτουργεί αποκλειστικά κατόπιν ραντεβού, ώστε να εξασφαλίζεται ο απαραίτητος χρόνος "
             "για κάθε ασθενή και να αποφεύγεται η αναμονή.", "#E8E2D4", 17, "center"),
        container(CALL_BTNS("center"), direction="row", inner=True, justify="center", gap=8),
    ], bg=NAVY, pad=box(75, 20, 75, 20), pad_m=box(50, 20, 50, 20), gap=14, align="center")


# =====================================================================
# HOME
# =====================================================================
hero = container([
    eyebrow("Νέα Μουδανιά · Χαλκιδική"),
    heading("Γαστρεντερολογικό – Ηπατολογικό Ιατρείο & Ενδοσκοπικό Κέντρο", "h1", WHITE, 52, 31, mb=18),
    text("Με διάθεση ολοκληρωμένης φροντίδας και προσφοράς, σας καλωσορίζουμε στο Γαστρεντερολογικό – "
         "Ηπατολογικό Ιατρείο μας.", "#E8E2D4", 20),
    container(CALL_BTNS(), direction="row", inner=True, gap=8),
], bg=NAVY, pad=box(120, 20, 110, 20), pad_m=box(60, 20, 55, 20), gap=14, min_h=520)

intro = section([
    cols(
        [eyebrow("Καλώς ήρθατε"),
         heading("Σύγχρονες και προσβάσιμες υπηρεσίες υγείας στη Χαλκιδική", "h2"),
         divider()],
        [text("Στον χώρο μας παρέχονται υψηλού επιπέδου, σύγχρονες και προσβάσιμες υπηρεσίες υγείας, καινοτόμες "
              "για το νομό Χαλκιδικής που συναγωνίζονται ευθέως παροχές αστικών κέντρων ή κέντρων του εξωτερικού."),
         text("Στο ιατρείο μας λειτουργεί πλήρως εξοπλισμένο ενδοσκοπικό εργαστήριο, με συστήματα τελευταίας γενιάς, "
              "το οποίο καλύπτει ένα ευρύ φάσμα ενδοσκοπικών εξετάσεων και πράξεων."),
         text("Στόχος μας είναι να προσφέρουμε τις υπηρεσίες αυτές στους κατοίκους της Χαλκιδικής, μόνιμους και μη, "
              "με αμεσότητα, εύκολη πρόσβαση και επιστημονική εγκυρότητα, εξατομικευμένα για τον κάθε ασθενή.")],
        widths=[42, 58], align="flex-start"),
], bg=WHITE, cid="kalosorisma")

SERVICES = [
    ("fas fa-stethoscope", "Κλινική εξέταση", "Πλήρες ιατρικό ιστορικό και κλινική εξέταση, με υπερηχογράφημα στο σημείο φροντίδας (POCUS) όπου χρειάζεται.", "klinikh"),
    ("fas fa-search-plus", "Γαστροσκόπηση", "Λεπτομερής έλεγχος οισοφάγου, στομάχου και δωδεκαδακτύλου, με ενδοφλέβια μέθη και συνεχή παρακολούθηση.", "gastroskopisi"),
    ("fas fa-microscope", "Κολονοσκόπηση & Πολυπεκτομή", "Έλεγχος ολόκληρου του παχέος εντέρου, πρόληψη και αφαίρεση πολυπόδων.", "kolonoskopisi"),
    ("fas fa-wave-square", "Εντερικό υπερηχογράφημα", "Μη επεμβατική αξιολόγηση του εντέρου, χωρίς ακτινοβολία, για νόσο Crohn και ελκώδη κολίτιδα.", "entheriko-yperixografima"),
    ("fas fa-heartbeat", "Ελαστογραφία ήπατος", "Shear Wave Ελαστογραφία για μη επεμβατική εκτίμηση της ηπατικής ίνωσης.", "elastografia"),
    ("fas fa-project-diagram", "Δίκτυο συνεργατών", "Έγκαιρη παραπομπή και συνέχεια φροντίδας για ERCP, EUS, μανομετρία και άλλες εξειδικευμένες πράξεις.", None),
]
cards = []
for ic, t, d, anchor in SERVICES:
    url = (L_SERV + "#" + anchor) if anchor else (L_COND + "#synergates")
    cards.append(container([icon_box(ic, t, d, url)], inner=True, width=33, bg=WHITE, radius=18, shadow=True,
                           pad=box(34, 24, 30, 24), gap=8))
services = section([
    eyebrow("Υπηρεσίες"),
    heading("Τι προσφέρουμε", "h2", align="left"),
    container(cards, direction="row", inner=True, gap=26),
    button("Όλες οι υπηρεσίες", L_SERV, NAVY, WHITE),
], bg=SAND_LIGHT)

why = section([
    cols(
        [eyebrow("Η προσέγγισή μας"),
         heading("Ασφάλεια, άνεση και ποιότητα σε κάθε εξέταση", "h2"),
         text("Βασική προτεραιότητα της ομάδας μας είναι η ασφάλεια, η φροντίδα και η άνεση του ασθενούς σε κάθε "
              "στάδιο της εξέτασης. Με επαγγελματισμό, διακριτικότητα και ανθρώπινη προσέγγιση, στόχος είναι κάθε "
              "ασθενής να αισθάνεται ότι βρίσκεται σε ένα ασφαλές και οργανωμένο περιβάλλον."),
         button("Γνωρίστε τον ιατρό", L_DOC, NAVY, WHITE)],
        [icon_list([
            "Σύγχρονη ενδοφλέβια μέθη για άνετη και ανώδυνη εμπειρία",
            "Συνεχής παρακολούθηση ζωτικών σημείων: monitor, οξυμετρία, καπνογραφία",
            "Ενδοσκόπια τελευταίας γενιάς για υψηλής ποιότητας απεικόνιση",
            "Εξειδικευμένο νοσηλευτικό προσωπικό με μακροχρόνια εμπειρία",
            "Αναλυτική ενημέρωση για τα ευρήματα και δυνατότητα προβολής των εικόνων",
        ], size=18, space=16)],
        widths=[50, 50], align="flex-start"),
], bg=WHITE)

save("01-arxiki.json", template("Αρχική", [hero, intro, services, why, cta_band()]))

# =====================================================================
# DOCTOR
# =====================================================================
doc_bio = section([
    cols(
        [image("", radius=20),
         text("<em>Προσθέστε εδώ τη φωτογραφία του ιατρού (Image widget → Choose Image).</em>", "#8A8F99", 13, "center")],
        [eyebrow("Ο Ιατρός"),
         heading("Η διαδρομή μας", "h2"),
         text("Μεγαλωμένος στα Νέα Μουδανιά Χαλκιδικής, με καταγωγή από τα Σήμαντρα και τη Νέα Τρίγλια, ξεκίνησε τις "
              "σπουδές του στην Ιατρική Σχολή του Αριστοτελείου Πανεπιστημίου Θεσσαλονίκης το 2008."),
         text("Αποφοίτησε το 2014, ολοκληρώνοντας τις σπουδές του σε έξι έτη. Μετά την ολοκλήρωση της στρατιωτικής "
              "του θητείας ως ιατρός στην 31η Ταξιαρχία στον Έβρο, εργάστηκε για τέσσερα έτη στο Γενικό Νοσοκομείο "
              "Χαλκιδικής, όπου εκπαιδεύτηκε στην Παθολογική Κλινική."),
         text("Συνέχισε την εκπαίδευσή του στη Γαστρεντερολογία στο Γενικό Νοσοκομείο Θεσσαλονίκης «Γ. Παπανικολάου», "
              "όπου ολοκλήρωσε την ειδικότητά του μετά από τέσσερα έτη εκπαίδευσης και κλινικής εργασίας στη "
              "Γαστρεντερολογική Κλινική."),
         text("Ακολούθησε η μετεκπαίδευσή του στη Γερμανία, με εξειδίκευση στον εντερικό υπέρηχο στο "
              "Πανεπιστημιακό Νοσοκομείο Carl Gustav Carus της Δρέσδης, διευρύνοντας περαιτέρω την κλινική και "
              "διαγνωστική του εμπειρία."),
         text("Το 2024 επέστρεψε στη Χαλκιδική, στον νομό όπου μεγάλωσε, με στόχο να μεταφέρει την εμπειρία και τις "
              "γνώσεις που απέκτησε όλα αυτά τα χρόνια στον τόπο του. Με αυτό το όραμα δημιουργήθηκε στα Νέα Μουδανιά "
              "ένα σύγχρονο Γαστρεντερολογικό – Ηπατολογικό Ιατρείο και Ενδοσκοπικό Κέντρο, με επίκεντρο τον ασθενή, "
              "τη σύγχρονη ιατρική και την παροχή ποιοτικών υπηρεσιών υγείας στον τόπο μας.")],
        widths=[36, 64], align="flex-start"),
], bg=WHITE)

timeline = section([
    eyebrow("Εκπαίδευση"),
    heading("Σταθμοί σταδιοδρομίας", "h2"),
    icon_list([
        "2008–2014 · Ιατρική Σχολή Αριστοτελείου Πανεπιστημίου Θεσσαλονίκης",
        "Στρατιωτική θητεία ως ιατρός · 31η Ταξιαρχία, Έβρος",
        "4 έτη · Παθολογική Κλινική, Γενικό Νοσοκομείο Χαλκιδικής",
        "4 έτη · Ειδικότητα Γαστρεντερολογίας, ΓΝ Θεσσαλονίκης «Γ. Παπανικολάου»",
        "Μετεκπαίδευση στον εντερικό υπέρηχο · Πανεπιστημιακό Νοσοκομείο Carl Gustav Carus, Δρέσδη",
        "Εκπαιδευτικό πρόγραμμα International Bowel Ultrasound Group (IBUS)",
        "2024 · Επιστροφή στη Χαλκιδική και ίδρυση του ιατρείου στα Νέα Μουδανιά",
    ], icon="fas fa-graduation-cap", size=18, space=16),
], bg=SAND_LIGHT)

staff = section([
    cols(
        [eyebrow("Το προσωπικό"), heading("Δίπλα σας σε κάθε στάδιο", "h2"), divider()],
        [text("Το νοσηλευτικό προσωπικό του κέντρου μας αποτελείται από επαγγελματίες υγείας με μακροχρόνια εμπειρία "
              "σε νοσηλευτικά ιδρύματα της Ελλάδας και του εξωτερικού, καθώς και εξειδικευμένη εμπειρία στον χώρο "
              "της γαστρεντερολογίας και των ενδοσκοπικών πράξεων."),
         text("Βασική προτεραιότητα της ομάδας μας είναι η ασφάλεια, η φροντίδα και η άνεση του ασθενούς σε κάθε "
              "στάδιο της εξέτασης. Από την προετοιμασία και την υποδοχή του ασθενούς έως την παρακολούθηση κατά τη "
              "διάρκεια της ενδοσκόπησης και την ανάνηψη μετά τη μέθη, το νοσηλευτικό προσωπικό βρίσκεται διαρκώς "
              "δίπλα του."),
         text("Η στενή συνεργασία μεταξύ ιατρού και νοσηλευτικού προσωπικού συμβάλλει στην ομαλή και ασφαλή "
              "διενέργεια των ενδοσκοπικών εξετάσεων, με συνεχή παρακολούθηση και άμεση ανταπόκριση στις ανάγκες "
              "κάθε ασθενούς."),
         text("Με επαγγελματισμό, διακριτικότητα και ανθρώπινη προσέγγιση, στόχος είναι κάθε ασθενής να αισθάνεται "
              "ότι βρίσκεται σε ένα ασφαλές και οργανωμένο περιβάλλον, όπου η ποιότητα της ιατρικής φροντίδας "
              "συνδυάζεται με τον σεβασμό και την προσωπική του ανάγκη.")],
        widths=[36, 64], align="flex-start"),
], bg=WHITE, cid="prosopiko")

save("02-o-iatros.json", template("Ο Ιατρός",
     [page_hero("Ο Ιατρός & η ομάδα", "Ο Ιατρός"), doc_bio, timeline, staff, cta_band()]))

# =====================================================================
# SERVICES
# =====================================================================
def svc(anchor, title, bg, left, right):
    return section([cols(left, right, widths=[50, 50], align="flex-start"), ], bg=bg, cid=anchor)


nav = container([
    container([button(t, "#" + a, SAND_LIGHT, NAVY) for t, a in [
        ("Κλινική εξέταση", "klinikh"), ("Γαστροσκόπηση", "gastroskopisi"), ("Κολονοσκόπηση", "kolonoskopisi"),
        ("Πολυπεκτομή", "polypektomi"), ("Εντερικό υπερηχογράφημα", "entheriko-yperixografima"),
        ("Ελαστογραφία ήπατος", "elastografia")]],
        direction="row", inner=True, gap=4, justify="center"),
], bg=WHITE, pad=box(26, 20, 18, 20), pad_m=box(20, 12, 12, 12))

s1 = svc("klinikh", "Κλινική εξέταση - Ραντεβού", WHITE,
         [eyebrow("Υπηρεσία"), heading("Κλινική εξέταση – Ραντεβού", "h2"), divider(),
          text("Ξεκινώντας με τη λήψη ενός πλήρους ιατρικού ιστορικού και την κλινική εξέταση, μπορούμε να "
               "διερευνήσουμε οξείες, υποξείες και χρόνιες παθήσεις του γαστρεντερικού συστήματος και του ήπατος."),
          text("Η κλινική εξέταση συμπληρώνεται, όπου κρίνεται απαραίτητο, με τη χρήση υπερηχογραφικής εξέτασης στο "
               "σημείο φροντίδας (Point-of-Care Ultrasound – POCUS), παρέχοντας τη δυνατότητα άμεσης "
               "συμπληρωματικής αξιολόγησης.")],
         [text("Το ιατρείο μας λειτουργεί <strong>αποκλειστικά κατόπιν ραντεβού</strong>, ώστε να εξασφαλίζεται ο "
               "απαραίτητος χρόνος για κάθε ασθενή, αλλά και να αποφεύγεται η άσκοπη αναμονή και ο συνωστισμός στον "
               "χώρο αναμονής."),
          accordion([("Τι να έχετε μαζί σας",
                      "<p>Για την καλύτερη και πληρέστερη αξιολόγηση, είναι χρήσιμο οι ασθενείς να προσκομίζουν στο "
                      "ραντεβού τους πρόσφατες εξετάσεις, όπως αιματολογικές και απεικονιστικές, καθώς και τα "
                      "αποτελέσματα προηγούμενων ενδοσκοπικών εξετάσεων.</p>"
                      "<p>Για την ολοκλήρωση της διαδικασίας, παρακαλείστε να έχετε μαζί σας τον ΑΜΚΑ σας ή, για "
                      "όσους διαθέτουν, την Ευρωπαϊκή Κάρτα Ασφάλισης Ασθενείας.</p>")]),
          button("Κλείστε ραντεβού", TEL1, NAVY, WHITE, icon="fas fa-phone-alt")])

specs_gastro = icon_list([
    "Σύγχρονη ενδοφλέβια μέθη, με στόχο μια άνετη και ανώδυνη εμπειρία για τον ασθενή.",
    "Συνεχής παρακολούθηση των ζωτικών σημείων καθ’ όλη τη διάρκεια της εξέτασης, με monitor, οξυμετρία και καπνογραφία.",
    "Ενδοσκόπια τελευταίας γενιάς, για υψηλής ποιότητας απεικόνιση και λεπτομερή έλεγχο του ανώτερου πεπτικού.",
])
s2 = svc("gastroskopisi", "Γαστροσκόπηση", SAND_LIGHT,
         [eyebrow("Υπηρεσία"), heading("Γαστροσκόπηση", "h2"), divider(),
          text("Στο κέντρο μας πραγματοποιείται ενδοσκόπηση ανώτερου πεπτικού με σύγχρονες μεθόδους και έμφαση στην "
               "ασφάλεια, την άνεση και την ποιότητα της εξέτασης."),
          text("<strong>Η εξέταση πραγματοποιείται με τις ακόλουθες προδιαγραφές:</strong>", mb=8), specs_gastro],
         [text("Με τη γαστροσκόπηση είναι δυνατός ο λεπτομερής έλεγχος του οισοφάγου, του στομάχου και του "
               "δωδεκαδακτύλου. Κατά τη διάρκεια της εξέτασης μπορεί να πραγματοποιηθεί λήψη δειγμάτων για την "
               "ανίχνευση του <em>Helicobacter pylori</em>, καθώς και λήψη βιοψιών από τον βλεννογόνο, όταν αυτό "
               "κρίνεται απαραίτητο."),
          text("Μετά την ολοκλήρωση της εξέτασης και αφού ο ασθενής ανανήψει από τη μέθη, ενημερώνεται αναλυτικά από "
               "τον ιατρό για τα ευρήματα. Παράλληλα, έχει τη δυνατότητα να δει τις εικόνες που καταγράφηκαν κατά τη "
               "διάρκεια της εξέτασης."),
          accordion([("Προετοιμασία για τη γαστροσκόπηση",
                      "<p>Για τη γαστροσκόπηση απαιτείται νηστεία από στερεά τροφή από το πρωί της ημέρας της "
                      "εξέτασης. Η κατανάλωση υγρών θα πρέπει να έχει διακοπεί τουλάχιστον δύο ώρες πριν από την "
                      "εξέταση, σύμφωνα με τις οδηγίες που θα σας δοθούν κατά τον προγραμματισμό του ραντεβού.</p>")])])

specs_colo = icon_list([
    "Σύγχρονη ενδοφλέβια μέθη, με στόχο μια άνετη και όσο το δυνατόν ανώδυνη εμπειρία για τον ασθενή.",
    "Συνεχής παρακολούθηση των ζωτικών σημείων καθ’ όλη τη διάρκεια της εξέτασης, με monitor, οξυμετρία και καπνογραφία.",
    "Ενδοσκόπια τελευταίας γενιάς, για υψηλής ποιότητας απεικόνιση και λεπτομερή έλεγχο του παχέος εντέρου.",
])
s3 = svc("kolonoskopisi", "Κολονοσκόπηση", WHITE,
         [eyebrow("Υπηρεσία"), heading("Κολονοσκόπηση", "h2"), divider(),
          text("Στο κέντρο μας πραγματοποιείται κολονοσκόπηση με σύγχρονες μεθόδους και έμφαση στην ασφάλεια, την "
               "άνεση και την ποιότητα της εξέτασης."),
          text("<strong>Η εξέταση πραγματοποιείται με τις ακόλουθες προδιαγραφές:</strong>", mb=8), specs_colo],
         [text("Με την κολονοσκόπηση είναι δυνατός ο λεπτομερής έλεγχος ολόκληρου του παχέος εντέρου και του τελικού "
               "τμήματος του λεπτού εντέρου με άμεση αξιολόγηση του βλεννογόνου. Κατά τη διάρκεια της εξέτασης "
               "μπορούν να ληφθούν βιοψίες από περιοχές που παρουσιάζουν παθολογικά ευρήματα, καθώς και να "
               "πραγματοποιηθεί αφαίρεση πολυπόδων, όταν αυτό κρίνεται απαραίτητο."),
          text("Η κολονοσκόπηση αποτελεί σημαντική εξέταση τόσο για τη διερεύνηση συμπτωμάτων και την παρακολούθηση "
               "γνωστών παθήσεων του εντέρου, όσο και για τον προληπτικό έλεγχο και την έγκαιρη ανίχνευση "
               "προκαρκινικών αλλοιώσεων και καρκίνου του παχέος εντέρου."),
          text("Μετά την ολοκλήρωση της εξέτασης και αφού ο ασθενής ανανήψει από τη μέθη, ενημερώνεται αναλυτικά από "
               "τον ιατρό για τα ευρήματα. Παράλληλα, έχει τη δυνατότητα να δει τις εικόνες που καταγράφηκαν κατά τη "
               "διάρκεια της εξέτασης και να ενημερωθεί για τυχόν βιοψίες ή άλλες ενδοσκοπικές πράξεις που "
               "πραγματοποιήθηκαν."),
          accordion([("Προετοιμασία για την κολονοσκόπηση",
                      "<p>Η σωστή προετοιμασία του εντέρου αποτελεί απαραίτητη προϋπόθεση για μια ασφαλή και "
                      "αξιόπιστη κολονοσκόπηση. Η προετοιμασία περιλαμβάνει ειδική διατροφή και τη λήψη καθαρτικού "
                      "σκευάσματος, σύμφωνα με εξατομικευμένες οδηγίες που θα σας δοθούν πριν από την εξέταση.</p>"
                      "<p>Η ακριβής τήρηση των οδηγιών προετοιμασίας είναι ιδιαίτερα σημαντική, καθώς ένα καλά "
                      "καθαρισμένο έντερο επιτρέπει την καλύτερη δυνατή απεικόνιση του βλεννογόνου και μειώνει την "
                      "πιθανότητα να μην εντοπιστούν μικρές αλλοιώσεις ή πολύποδες.</p>"
                      "<p>Την ημέρα της εξέτασης θα πρέπει να τηρηθούν οι οδηγίες σχετικά με τη νηστεία και την "
                      "κατανάλωση υγρών, καθώς και τυχόν ειδικές οδηγίες που αφορούν φαρμακευτική αγωγή, ιδιαίτερα σε "
                      "ασθενείς που λαμβάνουν αντιπηκτικά ή αντιδιαβητική αγωγή.</p>")])])

s4 = svc("polypektomi", "Πολυπεκτομή", SAND_LIGHT,
         [eyebrow("Υπηρεσία"), heading("Πολυπεκτομή", "h2"), divider(),
          text("Κατά τη διάρκεια της κολονοσκόπησης μπορεί να εντοπιστούν πολύποδες στο παχύ έντερο. Οι περισσότεροι "
               "πολύποδες είναι καλοήθεις, ορισμένοι όμως μπορεί, με την πάροδο του χρόνου, να εξελιχθούν σε "
               "προκαρκινικές αλλοιώσεις και, σπανιότερα, σε καρκίνο του παχέος εντέρου."),
          text("Στο κέντρο μας, η πολυπεκτομή είναι δυνατή για τους περισσότερους πολύποδες, είτε κατά τη διάρκεια "
               "της αρχικής κολονοσκόπησης είτε σε δεύτερο χρόνο, ανάλογα με τα χαρακτηριστικά του πολύποδα και με "
               "γνώμονα την ευκολία και, κυρίως, την ασφάλεια του ασθενούς.")],
         [text("Η πολυπεκτομή είναι η ενδοσκοπική αφαίρεση των πολυπόδων κατά τη διάρκεια της κολονοσκόπησης. "
               "Ανάλογα με το μέγεθος, τη μορφολογία και τη θέση του πολύποδα, επιλέγεται η κατάλληλη ενδοσκοπική "
               "τεχνική για την ασφαλή και πλήρη αφαίρεσή του."),
          text("Το υλικό που αφαιρείται αποστέλλεται για ιστολογική εξέταση, η οποία επιτρέπει τον ακριβή "
               "προσδιορισμό του τύπου και των χαρακτηριστικών του πολύποδα και συμβάλλει στον καθορισμό της "
               "περαιτέρω παρακολούθησης."),
          text("Η πολυπεκτομή αποτελεί σημαντικό μέρος της πρόληψης του καρκίνου του παχέος εντέρου, καθώς η έγκαιρη "
               "ανίχνευση και αφαίρεση προκαρκινικών πολυπόδων μπορεί να αποτρέψει την εξέλιξή τους σε κακοήθεια."),
          text("Μετά την ολοκλήρωση της εξέτασης, ο ασθενής ενημερώνεται αναλυτικά από τον ιατρό για τα ευρήματα, "
               "τους πολύποδες που εντοπίστηκαν και αφαιρέθηκαν, καθώς και για την ανάγκη περαιτέρω παρακολούθησης, "
               "ανάλογα με τα αποτελέσματα της ιστολογικής εξέτασης.")])

s5 = svc("entheriko-yperixografima", "Εντερικό υπερηχογράφημα", WHITE,
         [eyebrow("Υπηρεσία"), heading("Εντερικό υπερηχογράφημα", "h2"), divider(),
          text("Το εντερικό υπερηχογράφημα (Intestinal Ultrasound – IUS) αποτελεί μια σύγχρονη, μη επεμβατική και "
               "χωρίς ακτινοβολία μέθοδο για την άμεση αξιολόγηση του εντέρου."),
          text("Με τη χρήση ειδικού υπερηχογραφικού εξοπλισμού είναι δυνατή η λεπτομερής απεικόνιση του εντερικού "
               "τοιχώματος, καθώς και η αξιολόγηση της παρουσίας και της έκτασης φλεγμονής. Ιδιαίτερη χρησιμότητα "
               "έχει στη διάγνωση και παρακολούθηση των ιδιοπαθών φλεγμονωδών νόσων του εντέρου, όπως η νόσος Crohn "
               "και η ελκώδης κολίτιδα."),
          text("Ένα από τα σημαντικότερα πλεονεκτήματα της μεθόδου είναι ότι πραγματοποιείται άμεσα στο ιατρείο, "
               "χωρίς ακτινοβολία και χωρίς την ανάγκη ειδικής προετοιμασίας στις περισσότερες περιπτώσεις. Μπορεί, "
               "επομένως, να επαναλαμβάνεται όταν κρίνεται απαραίτητο, επιτρέποντας τη στενή παρακολούθηση της "
               "πορείας της νόσου και της ανταπόκρισης στη θεραπεία."),
          text("Το εντερικό υπερηχογράφημα μπορεί να χρησιμοποιηθεί συμπληρωματικά προς την κλινική εξέταση, τις "
               "εργαστηριακές εξετάσεις και την ενδοσκόπηση, προσφέροντας άμεση και αντικειμενική αξιολόγηση της "
               "κατάστασης του εντέρου.")],
         [container([
             heading("Εξειδίκευση στο εντερικό υπερηχογράφημα – IBUS", "h3", NAVY, 22, 20),
             text("Ο ιατρός έχει ολοκληρώσει το πλήρες εκπαιδευτικό πρόγραμμα του International Bowel Ultrasound "
                  "Group (IBUS), ενός διεθνούς επιστημονικού οργανισμού που δραστηριοποιείται στην εκπαίδευση, την "
                  "έρευνα και την κλινική εφαρμογή του εντερικού υπερηχογραφήματος."),
             text("Η εκπαίδευση περιλαμβάνει θεωρητική και πρακτική κατάρτιση, εκτεταμένη hands-on εκπαίδευση σε "
                  "εξειδικευμένο κέντρο και προχωρημένη εκπαίδευση στις σύγχρονες εφαρμογές του εντερικού "
                  "υπερηχογραφήματος, με έμφαση στη διάγνωση και παρακολούθηση των φλεγμονωδών νόσων του εντέρου."),
             text("Παράλληλα, ο ιατρός υπήρξε ένα από τα πρώτα μέλη του IBUS στην Ελλάδα, συμμετέχοντας από τα πρώτα "
                  "στάδια στην ανάπτυξη και διάδοση της μεθόδου στη χώρα."),
             text("Η εξειδικευμένη αυτή εκπαίδευση και εμπειρία επιτρέπει την αξιοποίηση του εντερικού "
                  "υπερηχογραφήματος στην καθημερινή κλινική πράξη, ιδιαίτερα για την αντικειμενική και "
                  "επαναλαμβανόμενη αξιολόγηση ασθενών με νόσο Crohn και ελκώδη κολίτιδα αλλά και άλλων εντερικών "
                  "παθήσεων."),
         ], inner=True, bg=SAND_LIGHT, radius=18, pad=box(32, 30, 28, 30), gap=10)])

s6 = svc("elastografia", "Shear Wave Ελαστογραφία ήπατος", SAND_LIGHT,
         [eyebrow("Υπηρεσία"), heading("Shear Wave Ελαστογραφία ήπατος", "h2"), divider(),
          text("Η Shear Wave Ελαστογραφία (SWE) αποτελεί μια σύγχρονη, μη επεμβατική υπερηχογραφική μέθοδο για την "
               "εκτίμηση της σκληρότητας του ηπατικού παρεγχύματος και, κατ’ επέκταση, τη μη επεμβατική αξιολόγηση "
               "του βαθμού ηπατικής ίνωσης."),
          text("Στο κέντρο μας προσφέρεται η εξέταση αυτή στο πλαίσιο της ηπατολογικής εξέτασης και παρακολούθησης, "
               "καθώς η ελαστογραφία αποτελεί απαραίτητο βοήθημα της σύγχρονης ηπατολογίας."),
          text("Κατά την εξέταση, ειδικά υπερηχογραφικά κύματα δημιουργούν και ανιχνεύουν κύματα διάτμησης (shear "
               "waves) μέσα στον ηπατικό ιστό. Η ταχύτητα διάδοσής τους χρησιμοποιείται για τον υπολογισμό της "
               "ηπατικής σκληρότητας, παρέχοντας ποσοτική πληροφορία που μπορεί να συμβάλει στη σταδιοποίηση της "
               "ίνωσης.")],
         [text("Η μέθοδος είναι ανώδυνη, γρήγορη και μη επεμβατική, χωρίς ακτινοβολία, και μπορεί να "
               "πραγματοποιηθεί στο πλαίσιο του υπερηχογραφικού ελέγχου του ήπατος. Αποτελεί χρήσιμο εργαλείο στην "
               "αξιολόγηση και παρακολούθηση ασθενών με χρόνια ηπατική νόσο, όπως η μεταβολικά σχετιζόμενη λιπώδης "
               "νόσος του ήπατος (MASLD), η ιογενής ηπατίτιδα και άλλες χρόνιες ηπατοπάθειες."),
          text("Η ελαστογραφία δεν αντικαθιστά την κλινική εκτίμηση ή τις εργαστηριακές εξετάσεις, αλλά αποτελεί "
               "σημαντικό μέρος της σύγχρονης μη επεμβατικής αξιολόγησης της ηπατικής ίνωσης και μπορεί να "
               "συμβάλει στην αναγνώριση ασθενών με αυξημένο κίνδυνο προχωρημένης ίνωσης."),
          text("Η εξέταση μπορεί να επαναλαμβάνεται κατά την παρακολούθηση, όταν αυτό κρίνεται απαραίτητο, "
               "επιτρέποντας την αξιολόγηση της πορείας της ηπατικής νόσου σε συνδυασμό με τα υπόλοιπα κλινικά και "
               "εργαστηριακά δεδομένα.")])

save("03-ypiresies.json", template("Υπηρεσίες",
     [page_hero("Υπηρεσίες", "Υπηρεσίες", "Σύγχρονες διαγνωστικές και ενδοσκοπικές εξετάσεις, με έμφαση στην ασφάλεια, "
                "την άνεση και την ποιότητα."),
      nav, s1, s2, s3, s4, s5, s6, cta_band()]))

# =====================================================================
# CONDITIONS + NETWORK
# =====================================================================
cond_left = [
    "Γαστροοισοφαγική παλινδρόμηση", "Γαστρίτιδα", "Λειτουργική δυσπεψία", "Σύνδρομο ευερέθιστου εντέρου",
    "Διερεύνηση χρόνιου διαρροϊκού συνδρόμου", "Δυσκοιλιότητα",
    "Ιδιοπαθείς φλεγμονώδεις νόσοι του εντέρου: ελκώδης κολίτιδα, νόσος Crohn",
    "Εκκολπωματική νόσος και εκκολπωματίτιδα", "Αιμορροιδοπάθεια και ραγάδα πρωκτού", "Κοιλιοκάκη"]
cond_right = [
    "Χρόνιες ιογενείς ηπατίτιδες", "Διερεύνηση αυξημένων τρανσαμινασών",
    "Μεταβολικά σχετιζόμενη λιπώδης νόσος του ήπατος (MASLD)", "Κίρρωση του ήπατος", "Ασκίτης",
    "Αυτοάνοσες παθήσεις του ήπατος και του χοληφόρου συστήματος", "Παθήσεις των χοληφόρων και του παγκρέατος"]

conds = section([
    text("Ενδεικτικά, παρακάτω αναφέρονται ορισμένες από τις συχνότερες παθήσεις του γαστρεντερικού συστήματος και "
         "του ήπατος που αντιμετωπίζονται και παρακολουθούνται στο κέντρο μας."),
    cols([heading("Γαστρεντερικό σύστημα", "h3", NAVY, 24, 21), icon_list(cond_left, space=12)],
         [heading("Ήπαρ, χοληφόρα και πάγκρεας", "h3", NAVY, 24, 21), icon_list(cond_right, space=12)],
         widths=[50, 50], align="flex-start", card=True),
], bg=SAND_LIGHT)

network = section([
    cols([eyebrow("Δίκτυο συνεργατών"), heading("Ολοκληρωμένη φροντίδα, όταν χρειάζεται περαιτέρω διερεύνηση", "h2"),
          divider(),
          text("Το κέντρο μας διαθέτει ένα οργανωμένο δίκτυο έμπειρων και εξειδικευμένων συνεργατών, με στόχο την "
               "ολοκληρωμένη αντιμετώπιση και παρακολούθηση των ασθενών, όταν απαιτείται περαιτέρω εξειδικευμένη "
               "διερεύνηση ή θεραπευτική παρέμβαση."),
          text("Στόχος του δικτύου συνεργατών είναι η έγκαιρη παραπομπή, η σωστή επιλογή της κατάλληλης "
               "θεραπευτικής προσέγγισης και η συνέχεια της φροντίδας του ασθενούς, σε συνεργασία με τους "
               "αντίστοιχους εξειδικευμένους ιατρούς.")],
         [text("Μέσω της συνεργασίας με εξειδικευμένους ιατρούς και κέντρα, υπάρχει δυνατότητα παραπομπής και "
               "συντονισμένης αντιμετώπισης σε ένα ευρύ φάσμα γαστρεντερολογικών παθήσεων και εξειδικευμένων "
               "πράξεων, όπως:"),
          icon_list([
              "ERCP και θεραπευτικές παρεμβάσεις των χοληφόρων και του παγκρέατος",
              "Ενδοσκοπικό υπερηχογράφημα (EUS)",
              "Μανομετρία και εμπεδησιομετρία οισοφάγου, για τη διερεύνηση διαταραχών κινητικότητας και "
              "γαστροοισοφαγικής παλινδρόμησης",
              "Επεμβατική και προηγμένη ενδοσκόπηση",
              "Τοποθέτηση γαστροστομίας",
              "Ογκολογική αντιμετώπιση παθήσεων του πεπτικού",
              "Χειρουργική αντιμετώπιση παθήσεων του πεπτικού συστήματος"], space=12)],
         widths=[45, 55], align="flex-start"),
], bg=WHITE, cid="synergates")

save("04-pathiseis.json", template("Παθήσεις & Δίκτυο συνεργατών",
     [page_hero("Παθήσεις", "Παθήσεις που αντιμετωπίζουμε"), conds, network, cta_band()]))

# =====================================================================
# CONTACT
# =====================================================================
contact = section([
    cols(
        [eyebrow("Επικοινωνία"), heading("Επικοινωνήστε μαζί μας", "h2"), divider(),
         text(tr("Επικοινωνήστε μαζί μας για να κλείσετε ραντεβού στο <strong>%s</strong> ή στο <strong>%s</strong> "
              "για να μιλήσετε απευθείας με τον ιατρό.") % (TEL1_TXT, TEL2_TXT)),
         button(tr("Ραντεβού") + " · " + TEL1_TXT, TEL1, NAVY, WHITE, icon="fas fa-phone-alt"),
         button(tr("Ο ιατρός") + " · " + TEL2_TXT, TEL2, SAND, NAVY, icon="fas fa-user-md"),
         button("Οδηγίες στο Google Maps", MAPS, WHITE, NAVY, outline=True, ext=True, icon="fas fa-map-marker-alt"),
         spacer(6),
         social([("fab fa-instagram", INSTA)]),
         text("Instagram: <a href=\"%s\" target=\"_blank\" rel=\"noopener\">@haris_eftaxias</a>" % INSTA, size=16)],
        [gmap("Νέα Μουδανιά, Χαλκιδική", 420)],
        widths=[44, 56], align="flex-start"),
], bg=WHITE)

reviews = section([
    heading("Η γνώμη των ασθενών μας", "h2", align="center"),
    text("Διαβάστε τις αξιολογήσεις των ασθενών μας στο Google ή αφήστε τη δική σας εμπειρία.", align="center"),
    container([button("Δείτε τις αξιολογήσεις στο Google", MAPS, NAVY, WHITE, ext=True, icon="fab fa-google",
                      align="center")],
              direction="row", inner=True, justify="center"),
], bg=SAND_LIGHT, align="center")

save("05-epikoinonia.json", template("Επικοινωνία",
     [page_hero("Επικοινωνία", "Επικοινωνία"), contact, reviews]))

# =====================================================================
# HEADER / FOOTER for the free "Header Footer Elementor" plugin
# =====================================================================
MENU = [("Αρχική", L_HOME), ("Ο Ιατρός", L_DOC), ("Υπηρεσίες", L_SERV), ("Παθήσεις", L_COND), ("Επικοινωνία", L_CONT)]

header = container([
    container([image("", radius=0)], inner=True, width=20),
    container([container([button(t, u, "rgba(255,255,255,0)", NAVY) for t, u in MENU], direction="row", inner=True,
                         gap=2, justify="center")], inner=True, width=55),
    container([button(tr("Κλήση") + ": " + TEL1_TXT, TEL1, NAVY, WHITE, icon="fas fa-phone-alt", align="right"),
               text("<a href='/'>EL</a> &nbsp;·&nbsp; <a href='/en/'>EN</a> &nbsp;·&nbsp; <a href='/de/'>DE</a>",
                    NAVY, 14, "right")],
              inner=True, width=25, gap=4),
], direction="row", bg=WHITE, pad=box(14, 20, 14, 20), align="center", gap=10)
save("header.json", template("Header", [header], "section"))

footer = container([
    cols([text("<strong style='color:#fff'>" + tr("Γαστρεντερολογικό – Ηπατολογικό Ιατρείο & Ενδοσκοπικό Κέντρο")
              + "</strong><br>" + tr("Νέα Μουδανιά Χαλκιδικής"), "#E8E2D4", 15)],
         [text(tr("Τηλ. ραντεβού") + ": <a style='color:#fff' href='%s'>%s</a><br>" % (TEL1, TEL1_TXT)
              + tr("Απευθείας με τον ιατρό") + ": <a style='color:#fff' href='%s'>%s</a>" % (TEL2, TEL2_TXT),
              "#E8E2D4", 15)],
         [icon_list([(t, u) for t, u in MENU], icon="fas fa-angle-right", color=SAND, text_color="#E8E2D4", size=15, space=6)],
         widths=[38, 32, 30], align="flex-start"),
    divider("#3B5273", 100, 1),
    text("© 2026 · " + tr("Οι πληροφορίες του ιστότοπου είναι ενημερωτικού χαρακτήρα και δεν υποκαθιστούν την "
                          "ιατρική εξέταση ή συμβουλή."), "#9FB0C8", 13, "center"),
], bg=NAVY, pad=box(60, 20, 30, 20), pad_m=box(40, 20, 24, 20), gap=22)
save("footer.json", template("Footer", [footer], "section"))
if LANG == "collect":
    json.dump(COLLECTED, open(os.path.join(OUT, "strings.json"), "w", encoding="utf8"), ensure_ascii=False, indent=0)
    print(len(COLLECTED), "strings")
elif MISSING:
    print("MISSING", len(MISSING)); [print(" -", m[:80]) for m in dict.fromkeys(MISSING)]; sys.exit(1)
else:
    print("ok", LANG)

"""Local InsightFace matcher for attendance punches.

Start:
    python3 -m venv .venv
    .venv/bin/pip install -r requirements.txt
    .venv/bin/python app.py
"""

import os

import cv2
import numpy as np
from flask import Flask, jsonify, request
from insightface.app import FaceAnalysis

HOST = os.environ.get("INSIGHTFACE_HOST", "127.0.0.1")
PORT = int(os.environ.get("INSIGHTFACE_PORT", "8011"))
MODEL = os.environ.get("INSIGHTFACE_MODEL", "buffalo_s")
DEFAULT_THRESHOLD = float(os.environ.get("INSIGHTFACE_MIN_SIMILARITY", "0.40"))

server = Flask(__name__)
analyzer = FaceAnalysis(name=MODEL, providers=["CPUExecutionProvider"])
analyzer.prepare(ctx_id=-1, det_size=(640, 640))


def decode_image(upload):
    payload = np.frombuffer(upload.read(), dtype=np.uint8)
    image = cv2.imdecode(payload, cv2.IMREAD_COLOR)

    if image is None:
        return None

    return image


def largest_face(image):
    faces = analyzer.get(image)

    if not faces:
        return None

    return max(faces, key=lambda face: (face.bbox[2] - face.bbox[0]) * (face.bbox[3] - face.bbox[1]))


@server.get("/health")
def health():
    return jsonify({"ok": True, "model": MODEL})


@server.post("/compare")
def compare():
    profile = request.files.get("profile")
    selfie = request.files.get("selfie")

    if profile is None or selfie is None:
        return jsonify({"message": "Profile photo and selfie are required."}), 422

    try:
        threshold = float(request.form.get("threshold", DEFAULT_THRESHOLD))
    except (TypeError, ValueError):
        threshold = DEFAULT_THRESHOLD

    profile_image = decode_image(profile)
    selfie_image = decode_image(selfie)

    if profile_image is None or selfie_image is None:
        return jsonify({
            "matched": False,
            "similarity": 0,
            "percent": 0,
            "message": "Could not read one of the photos.",
        })

    profile_face = largest_face(profile_image)
    selfie_face = largest_face(selfie_image)

    if profile_face is None:
        return jsonify({
            "matched": False,
            "similarity": 0,
            "percent": 0,
            "message": "No face was found in the approved profile photo.",
        })

    if selfie_face is None:
        return jsonify({
            "matched": False,
            "similarity": 0,
            "percent": 0,
            "message": "No face was found in the punch photo. Look at the camera and try again.",
        })

    similarity = float(np.dot(profile_face.normed_embedding, selfie_face.normed_embedding))
    percent = round(max(0.0, similarity) * 100, 2)

    return jsonify({
        "matched": similarity >= threshold,
        "similarity": round(similarity, 4),
        "percent": percent,
        "threshold": threshold,
        "message": None,
    })


if __name__ == "__main__":
    server.run(host=HOST, port=PORT, threaded=True)

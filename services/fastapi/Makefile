install:
	python -m pip install --upgrade pip
	python -m pip install -r requirements-dev.txt

run:
	uvicorn main:app --host 0.0.0.0 --port 8001 --reload

test:
	pytest -q

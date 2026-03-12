import sys
import json
import os
import pandas as pd


def load_dataframe(file_path, file_type):
    file_path = os.path.normpath(file_path)
    if not os.path.exists(file_path):
        raise FileNotFoundError(f"File not found: {file_path}")

    allowed = ['csv', 'txt', 'xml', 'xlsx', 'json', 'parquet']
    if file_type not in allowed:
        raise ValueError(f"Unsupported file type: {file_type}")

    if file_type == 'csv':
        return pd.read_csv(file_path)
    if file_type == 'txt':
        try:
            return pd.read_csv(file_path, sep=',')
        except Exception:
            try:
                return pd.read_csv(file_path, sep='\t')
            except Exception:
                return pd.read_csv(file_path, sep=r'\s+', engine='python')
    if file_type == 'xml':
        return pd.read_xml(file_path)
    if file_type == 'xlsx':
        return pd.read_excel(file_path, engine='openpyxl')
    if file_type == 'json':
        return pd.read_json(file_path)
    if file_type == 'parquet':
        return pd.read_parquet(file_path)

    raise ValueError(f"Unsupported file type: {file_type}")


def main():
    try:
        if len(sys.argv) < 2:
            raise ValueError("Missing required JSON payload")

        payload = json.loads(sys.argv[1])
        file_path = payload.get("file_path")
        file_type = payload.get("file_type")
        target_format = (payload.get("target_format") or "").lower()
        output_path = payload.get("output_path")

        if not file_path or not file_type or not target_format or not output_path:
            raise ValueError("file_path, file_type, target_format, and output_path are required")

        df = load_dataframe(file_path, file_type)

        os.makedirs(os.path.dirname(output_path), exist_ok=True)

        if target_format == "csv":
            df.to_csv(output_path, index=False)
        elif target_format == "xlsx":
            df.to_excel(output_path, index=False, engine="openpyxl")
        elif target_format == "json":
            df.to_json(output_path, orient="records")
        elif target_format == "parquet":
            df.to_parquet(output_path, index=False)
        else:
            raise ValueError(f"Unsupported export format: {target_format}")

        print(json.dumps({
            "success": True,
            "output_path": output_path,
            "target_format": target_format,
        }))
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": str(e),
            "error_type": type(e).__name__,
        }))
        sys.exit(1)


if __name__ == "__main__":
    main()


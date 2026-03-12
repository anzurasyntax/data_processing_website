import sys
import json
import os
import pandas as pd
import numpy as np

def to_native(obj):
    """Convert numpy/pandas types to native Python types for JSON serialization."""
    # Handle None first
    if obj is None:
        return None
    
    # Handle numpy types (NumPy 2.0 compatible - removed np.int_ and np.float_)
    if isinstance(obj, (np.integer, np.intc, np.intp, np.int8, np.int16, np.int32, np.int64)):
        return int(obj)
    if isinstance(obj, (np.floating, np.float16, np.float32, np.float64)):
        return float(obj)
    if isinstance(obj, (np.bool_,)):
        return bool(obj)
    
    # Handle pandas types
    if isinstance(obj, (np.ndarray,)):
        return obj.tolist()
    if isinstance(obj, (pd.Series,)):
        return obj.tolist()
    if isinstance(obj, (pd.Index,)):
        return obj.tolist()
    if isinstance(obj, (pd.Timestamp,)):
        return obj.strftime("%Y-%m-%d %H:%M:%S")
    
    # Check for NaN/NA values (handles pd.NA, np.nan, None, etc.)
    try:
        if pd.isna(obj):
            return None
    except (TypeError, ValueError, AttributeError):
        pass
    
    # Handle collections
    if isinstance(obj, dict):
        return {str(key): to_native(value) for key, value in obj.items()}
    if isinstance(obj, (list, tuple)):
        return [to_native(item) for item in obj]
    if isinstance(obj, set):
        return [to_native(item) for item in obj]
    
    # Handle basic Python types that are JSON-serializable
    if isinstance(obj, (str, int, float, bool)):
        return obj
    
    # For any other type, try to convert to string as last resort
    try:
        return str(obj)
    except:
        return None

def load_file(file_path, file_type):
    """Load file based on type with proper error handling."""
    file_path = os.path.normpath(file_path)
    
    if not os.path.exists(file_path):
        raise FileNotFoundError(f"File not found: {file_path}")
    
    allowed_types = ['csv', 'txt', 'xml', 'xlsx']
    if file_type not in allowed_types:
        raise ValueError(f"Unsupported file type: {file_type}")
    
    try:
        if file_type == 'csv':
            # Performance: allow chunk loading for large files (fallback to full read)
            try:
                df = pd.read_csv(file_path, chunksize=10000)
                df = pd.concat(df, ignore_index=True)
            except Exception:
                df = pd.read_csv(file_path)
        elif file_type == 'txt':
            try:
                df = pd.read_csv(file_path, sep=',', chunksize=10000)
                df = pd.concat(df, ignore_index=True)
            except:
                try:
                    df = pd.read_csv(file_path, sep='\t', chunksize=10000)
                    df = pd.concat(df, ignore_index=True)
                except:
                    try:
                        df = pd.read_csv(file_path, sep=r'\s+', engine='python', chunksize=10000)
                        df = pd.concat(df, ignore_index=True)
                    except Exception:
                        df = pd.read_csv(file_path, sep=r'\s+', engine='python')
        elif file_type == 'xml':
            df = pd.read_xml(file_path)
        elif file_type == 'xlsx':
            try:
                df = pd.read_excel(file_path, engine='openpyxl')
            except ImportError:
                raise ImportError("openpyxl library is required for Excel files")
            except Exception as e:
                raise Exception(f"Failed to read Excel file: {str(e)}")
        else:
            raise ValueError(f"Unsupported file type: {file_type}")
        
        return df
    except pd.errors.EmptyDataError:
        raise ValueError("The file is empty or contains no valid data")
    except pd.errors.ParserError as e:
        raise ValueError(f"Failed to parse file: {str(e)}")
    except Exception as e:
        raise Exception(f"Error reading file: {str(e)}")

def calculate_quality_metrics(df):
    """Calculate comprehensive quality metrics for the dataset."""
    total_rows = len(df)
    total_cols = len(df.columns)
    
    # Overall quality score (0-100)
    quality_score = 100
    issues = []
    issues_by_type = {
        'missing_values': [],
        'duplicates': [],
        'outliers': [],
        'data_types': [],
        'inconsistencies': []
    }
    
    # Column-level analysis
    column_quality = {}
    total_missing = 0
    total_duplicates = 0
    total_outliers = 0
    
    for col in df.columns:
        col_data = df[col]
        
        # Missing values (including NaN, empty strings, and whitespace-only strings)
        # Convert to string and check for empty or whitespace-only values
        str_col = col_data.astype(str)
        # str_col.str.strip() == '' catches both empty strings and whitespace-only strings
        missing_count = col_data.isna().sum() + (str_col.str.strip() == '').sum()
        missing_pct = (missing_count / total_rows) * 100 if total_rows > 0 else 0
        total_missing += missing_count
        
        # Duplicates
        duplicate_count = int(col_data.duplicated().sum())
        total_duplicates += duplicate_count
        
        # Data type detection
        numeric_values = pd.to_numeric(col_data, errors='coerce')
        non_null_numeric = numeric_values.dropna()
        
        if len(col_data.replace('', np.nan).dropna()) == 0:
            detected_type = "empty"
        else:
            numeric_ratio = numeric_values.notna().mean()
            if numeric_ratio > 0.8:
                detected_type = "number"
            elif numeric_ratio > 0:
                detected_type = "text-number"
            else:
                detected_type = "text"
        
        # Outlier detection (for numeric columns)
        outlier_count = 0
        outlier_indexes = []
        stats = {}
        
        if detected_type in ["number", "text-number"] and len(non_null_numeric) > 0:
            try:
                q1 = non_null_numeric.quantile(0.25)
                q3 = non_null_numeric.quantile(0.75)
                iqr = q3 - q1
                
                if iqr > 0:
                    lower = q1 - 1.5 * iqr
                    upper = q3 + 1.5 * iqr
                    outlier_mask = (numeric_values < lower) | (numeric_values > upper)
                    outlier_indexes = [int(idx) for idx in outlier_mask[outlier_mask].index.tolist()]
                    outlier_count = len(outlier_indexes)
                    total_outliers += outlier_count
                
                stats = {
                    "min": round(float(non_null_numeric.min()), 2),
                    "max": round(float(non_null_numeric.max()), 2),
                    "mean": round(float(non_null_numeric.mean()), 2),
                    "median": round(float(non_null_numeric.median()), 2),
                    "std": round(float(non_null_numeric.std()), 2) if len(non_null_numeric) > 1 else 0.0
                }
            except:
                pass
        
        # Quality issues for this column
        col_issues = []
        if missing_count > 0:
            col_issues.append({
                'type': 'missing_values',
                'severity': 'high' if missing_pct > 50 else 'medium' if missing_pct > 20 else 'low',
                'message': f"{missing_count} missing values ({missing_pct:.1f}%)"
            })
            issues_by_type['missing_values'].append({
                'column': col,
                'count': int(missing_count),
                'percentage': round(missing_pct, 1)
            })
        
        if duplicate_count > 0:
            col_issues.append({
                'type': 'duplicates',
                'severity': 'medium',
                'message': f"{duplicate_count} duplicate values"
            })
            issues_by_type['duplicates'].append({
                'column': col,
                'count': int(duplicate_count)
            })
        
        if outlier_count > 0:
            col_issues.append({
                'type': 'outliers',
                'severity': 'medium',
                'message': f"{outlier_count} outliers detected"
            })
            issues_by_type['outliers'].append({
                'column': col,
                'count': int(outlier_count)
            })
        
        column_quality[col] = {
            'missing_count': int(missing_count),
            'missing_percentage': round(missing_pct, 1),
            'duplicate_count': int(duplicate_count),
            'outlier_count': int(outlier_count),
            'data_type': detected_type,
            'unique_values': int(col_data.nunique()),
            'stats': stats,
            'issues': col_issues
        }
    
    # Row-level duplicates
    duplicate_rows = int(df.duplicated().sum())
    
    # Calculate overall quality score
    if total_rows > 0:
        missing_penalty = (total_missing / (total_rows * total_cols)) * 30
        duplicate_penalty = (duplicate_rows / total_rows) * 20
        outlier_penalty = min((total_outliers / (total_rows * total_cols)) * 20, 20)
        
        quality_score = max(0, 100 - missing_penalty - duplicate_penalty - outlier_penalty)
    
    # Determine if data is clean
    is_clean = (
        total_missing == 0 and
        duplicate_rows == 0 and
        total_outliers == 0
    )
    
    # Summary issues
    if total_missing > 0:
        issues.append({
            'type': 'missing_values',
            'severity': 'high' if (total_missing / (total_rows * total_cols)) > 0.2 else 'medium',
            'message': f"{total_missing} missing values across {len([c for c in column_quality.values() if c['missing_count'] > 0])} columns"
        })
    
    if duplicate_rows > 0:
        issues.append({
            'type': 'duplicates',
            'severity': 'medium',
            'message': f"{duplicate_rows} duplicate rows found"
        })
    
    if total_outliers > 0:
        issues.append({
            'type': 'outliers',
            'severity': 'low',
            'message': f"{total_outliers} outliers detected across {len([c for c in column_quality.values() if c['outlier_count'] > 0])} columns"
        })
    
    return {
        'quality_score': round(quality_score, 1),
        'is_clean': is_clean,
        'total_rows': total_rows,
        'total_columns': total_cols,
        'total_missing': int(total_missing),
        'total_duplicate_rows': duplicate_rows,
        'total_outliers': int(total_outliers),
        'issues': issues,
        'issues_by_type': issues_by_type,
        'column_quality': column_quality
    }

def main():
    try:
        if len(sys.argv) < 2:
            raise ValueError("Missing required argument: JSON payload")
        
        payload = json.loads(sys.argv[1])
        file_path = payload.get("file_path")
        file_type = payload.get("file_type")
        
        if not file_path or not file_type:
            raise ValueError("file_path and file_type are required")
        
        # Load file
        df = load_file(file_path, file_type)
        
        # Calculate quality metrics
        quality_metrics = calculate_quality_metrics(df)
        
        result = {
            "success": True,
            **quality_metrics
        }
        
        # Recursively convert all pandas/numpy objects to native types
        # This should eliminate any circular references
        result = to_native(result)
        
        # Serialize to JSON
        # Use default=to_native as fallback for any remaining non-serializable objects
        json_output = json.dumps(result, default=to_native, ensure_ascii=False)
        
        print(json_output)
        sys.stdout.flush()  # Ensure output is flushed
        
    except json.JSONDecodeError as e:
        error_result = {
            "success": False,
            "error": f"Invalid JSON payload: {str(e)}",
            "error_type": "JSONDecodeError"
        }
        print(json.dumps(error_result))
        sys.stdout.flush()
        sys.exit(1)
    except Exception as e:
        error_result = {
            "success": False,
            "error": str(e),
            "error_type": type(e).__name__
        }
        print(json.dumps(error_result))
        sys.stdout.flush()
        sys.exit(1)

if __name__ == "__main__":
    main()

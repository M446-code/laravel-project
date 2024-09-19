<!DOCTYPE html>
<html>
<head>
    <title>Route List</title>
    <style>
        body {
            font-family: Consolas, Monaco, 'Andale Mono', 'Ubuntu Mono', monospace;
            background-color: #f0f0f0;
            margin: 0;
            padding: 0;
        }

        h1 {
            background-color: #333;
            color: #fff;
            padding: 10px;
            text-align: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            border: 1px solid #ddd;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #333;
            color: #fff;
        }

        tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        tr:nth-child(odd) {
            background-color: #fff;
        }
    </style>
</head>
<body>
    <h1>List of Routes</h1>

    <table>
        <thead>
            <tr>
                <th>Methods</th>
                <th>URI</th>
                <th>Name</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($routes as $route)
            <tr>
                <td>{{ implode(', ', $route->methods) }}</td>
                <td>{{ $route->uri }}</td>
                <td>{{ $route->getName() }}</td>
                <td>{{ $route->getActionName() }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>

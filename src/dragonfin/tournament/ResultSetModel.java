package dragonfin.tournament;

import java.sql.*;
import java.util.*;
import javax.swing.table.*;

public class ResultSetModel extends AbstractTableModel
{
	ResultSet rs;
	int columnCount;
	String [] columnNames;
	Object [][] data;

	public ResultSetModel(ResultSet rs)
		throws SQLException
	{
		this.rs = rs;

		ResultSetMetaData rsmd = rs.getMetaData();
		this.columnCount = rsmd.getColumnCount();

		this.columnNames = new String[columnCount];
		for (int i = 0; i < columnCount; i++) {
			this.columnNames[i] = rsmd.getColumnName(i+1);
		}

		ArrayList< Object[] > rows = new ArrayList< Object[] >();
		while (rs.next()) {
			Object[] r = new Object[columnCount];
			for (int i = 0; i < columnCount; i++) {
				r[i] = rs.getObject(i+1);
			}
			rows.add(r);
		}

		this.data = rows.toArray(new Object[0][]);
	}

	@Override
	public String getColumnName(int col)
	{
		assert col >= 0 && col < columnCount;

		return columnNames[col];
	}

	@Override
	public int getRowCount()
	{
		return data.length + 1;
	}

	@Override
	public int getColumnCount()
	{
		return columnCount;
	}

	@Override
	public boolean isCellEditable(int row, int col)
	{
		return col != 0;
	}

	@Override
	public Object getValueAt(int row, int col)
	{
		if (row < data.length) {
			return data[row][col];
		}
		else {
			return null;
		}
	}

	@Override
	public void setValueAt(Object value, int row, int col)
	{
		if (row < data.length) {
			data[row][col] = value;
			fireTableCellUpdated(row, col);
		}
		else {
			//TODO
		}
	}
}

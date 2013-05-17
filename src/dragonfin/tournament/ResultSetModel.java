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
	boolean showInsertRow;

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
		return data.length + (showInsertRow ? 1 : 0);
	}

	@Override
	public int getColumnCount()
	{
		return columnCount;
	}

	@Override
	public boolean isCellEditable(int row, int col)
	{
		return col != 0 && updateHandler != null;
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

			try {
			updateHandler.update(data[row][0], columnNames[col], data[row][col]);
			} catch (SQLException e) {
				throw new RuntimeException(e);
			}
		}
		else {
			//TODO- insert a row
		}
	}

	public interface UpdateHandler
	{
		void update(Object key, String attrName, Object newValue)
			throws SQLException;
	}
	UpdateHandler updateHandler;
}

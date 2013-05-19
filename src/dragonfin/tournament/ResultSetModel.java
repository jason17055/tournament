package dragonfin.tournament;

import java.awt.Component;
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
	boolean [] hiddenColumn;

	UpdateHandler updateHandler;
	Appender appendHandler;
	Lookup [] lookupColumn;

	public ResultSetModel(ResultSet rs)
		throws SQLException
	{
		this.rs = rs;

		ResultSetMetaData rsmd = rs.getMetaData();
		this.columnCount = rsmd.getColumnCount();

		this.columnNames = new String[columnCount];
		this.hiddenColumn = new boolean[columnCount];
		this.lookupColumn = new Lookup[columnCount];
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

	// example: col.0 is hidden
	// then   0 => 1
	//        1 => 2
	//	 etc.
	//
	// example: col.1 is hidden
	// then   0 => 0
	//        1 => 2
	//        2 => 3
	//       etc.
	//
	// example: col.0 and col.2 are hidden
	// then   0 => 1
	//        1 => 3
	//        2 => 4
	//       etc.
	//
	int adjustForHiddenColumns(int col)
	{
		for (int i = 0; i <= col; i++) {
			if (hiddenColumn[i]) {
				col++;
			}
		}

		assert col >= 0 && col < columnCount;
		return col;
	}

	@Override
	public String getColumnName(int col)
	{
		col = adjustForHiddenColumns(col);
		assert col >= 0 && col < columnCount;

		return columnNames[col];
	}

	@Override
	public int getRowCount()
	{
		return data.length
			+ (showInsertRow ? 1 : 0);
	}

	@Override
	public int getColumnCount()
	{
		int rv = columnCount;
		for (int i = 0; i < columnCount; i++) {
			if (hiddenColumn[i]) {
				rv--;
			}
		}
		return rv;
	}

	@Override
	public boolean isCellEditable(int row, int col)
	{
		col = adjustForHiddenColumns(col);
		assert col >= 0 && col < columnCount;

		return col != 0 && updateHandler != null;
	}

	public boolean isLookupColumn(int col)
	{
		col = adjustForHiddenColumns(col);
		return lookupColumn[col] != null;
	}

	public Lookup getLookupColumn(int col)
	{
		col = adjustForHiddenColumns(col);
		return lookupColumn[col];
	}

	@Override
	public Object getValueAt(int row, int col)
	{
		col = adjustForHiddenColumns(col);

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
		col = adjustForHiddenColumns(col);

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

	public interface Appender
	{
		void newRow()
			throws SQLException;
	}

	public interface Lookup
	{
		List<LookupItem> getLookupList();
		boolean showUnlistedOption();
		void doUnlistedOption(Component ownerComponent);
	}
}
